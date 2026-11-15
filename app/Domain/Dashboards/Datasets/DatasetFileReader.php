<?php

namespace App\Domain\Dashboards\Datasets;

use Generator;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Streams the rows of an uploaded CSV or Excel (.xlsx) file one at a time, so a file
 * never has to fit in memory. The first yielded row is the header.
 *
 * CSV files exported by Excel in Spanish are usually Windows-1252 with ";" as the
 * separator, so both the encoding and the delimiter are detected instead of assumed.
 */
final class DatasetFileReader
{
    private const DELIMITERS = [',', ';', "\t", '|'];

    /**
     * @return Generator<int, list<mixed>>
     *
     * @throws DatasetImportException when the file cannot be read
     */
    public function rows(string $path, string $type): Generator
    {
        try {
            yield from $type === 'xlsx' ? $this->xlsx($path) : $this->csv($path);
        } catch (DatasetImportException $exception) {
            throw $exception;
        } catch (Throwable) {
            // OpenSpout, zip and iconv failures all mean the same to the user.
            throw DatasetImportException::unreadable();
        }
    }

    /**
     * @return Generator<int, list<mixed>>
     */
    private function xlsx(string $path): Generator
    {
        $reader = new XlsxReader(new XlsxOptions(SHOULD_FORMAT_DATES: false));
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if (! $sheet->isVisible()) {
                    continue;
                }

                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $this->cells($row);

                    if ($cells !== null) {
                        yield $cells;
                    }
                }

                // Only the first visible sheet is imported.
                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * @return Generator<int, list<mixed>>
     */
    private function csv(string $path): Generator
    {
        [$readablePath, $encoding, $delimiter, $temporary] = $this->prepareCsv($path);

        $reader = new CsvReader(new CsvOptions(FIELD_DELIMITER: $delimiter, ENCODING: $encoding));

        try {
            $reader->open($readablePath);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $this->cells($row);

                    if ($cells !== null) {
                        yield $cells;
                    }
                }

                break;
            }
        } finally {
            $reader->close();

            if ($temporary) {
                @unlink($readablePath);
            }
        }
    }

    /**
     * Works out the encoding and delimiter, converting to UTF-8 first when needed.
     *
     * @return array{0: string, 1: string, 2: string, 3: bool} path to read, OpenSpout encoding, delimiter, whether the path is a temporary copy
     */
    private function prepareCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw DatasetImportException::unreadable();
        }

        $head = (string) fread($handle, 65536);
        fclose($handle);

        $encoding = 'UTF-8';
        $sample = $head;

        if (str_starts_with($head, "\xFF\xFE")) {
            $encoding = 'UTF-16LE';
            $sample = (string) mb_convert_encoding(substr($head, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($head, "\xFE\xFF")) {
            $encoding = 'UTF-16BE';
            $sample = (string) mb_convert_encoding(substr($head, 2), 'UTF-8', 'UTF-16BE');
        } elseif (str_starts_with($head, "\xEF\xBB\xBF")) {
            $sample = substr($head, 3);
        }

        $temporary = false;
        $readable = $path;

        if ($encoding === 'UTF-8' && ! $this->looksLikeUtf8($sample)) {
            // Not UTF-8: Excel for Windows saves "CSV" in Windows-1252.
            $readable = $this->convertToUtf8($path);
            $temporary = true;
            $sample = (string) mb_convert_encoding($sample, 'UTF-8', 'Windows-1252');
        }

        return [$readable, $encoding, $this->detectDelimiter($sample), $temporary];
    }

    private function looksLikeUtf8(string $sample): bool
    {
        // The sample can end in the middle of a multi-byte character.
        for ($trim = 0; $trim < 4 && $trim < strlen($sample); $trim++) {
            if (mb_check_encoding(substr($sample, 0, strlen($sample) - $trim), 'UTF-8')) {
                return true;
            }
        }

        return $sample === '';
    }

    private function convertToUtf8(string $path): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'dataset_');
        $in = fopen($path, 'rb');
        $out = $temporary === false ? false : fopen($temporary, 'wb');

        if ($in === false || $out === false || $temporary === false) {
            throw DatasetImportException::unreadable();
        }

        stream_filter_append($in, 'convert.iconv.CP1252.UTF-8');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        return $temporary;
    }

    private function detectDelimiter(string $sample): string
    {
        $firstLine = '';

        foreach (preg_split('/\r\n|\n|\r/', $sample) ?: [] as $line) {
            if (trim($line) !== '') {
                $firstLine = $line;

                break;
            }
        }

        $best = ',';
        $bestCount = 0;

        foreach (self::DELIMITERS as $delimiter) {
            $count = substr_count($firstLine, $delimiter);

            if ($count > $bestCount) {
                $best = $delimiter;
                $bestCount = $count;
            }
        }

        return $best;
    }

    /**
     * @return list<mixed>|null null for a row with no content at all
     */
    private function cells(Row $row): ?array
    {
        $cells = array_values($row->toArray());

        foreach ($cells as $cell) {
            if (! ValueParser::isEmpty($cell)) {
                return $cells;
            }
        }

        return null;
    }
}
