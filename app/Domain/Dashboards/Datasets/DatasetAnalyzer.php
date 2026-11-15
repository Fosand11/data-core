<?php

namespace App\Domain\Dashboards\Datasets;

use App\Domain\Dashboards\Enums\DatasetColumnType;

/**
 * Reads the start of an uploaded file and proposes its columns: a safe key, the header
 * as written, a guessed type and a few example values for the preview. Nothing is
 * stored here; the user confirms or corrects the proposal before anything is imported.
 */
final class DatasetAnalyzer
{
    /** A column is numeric / a date when at least this share of its non-empty values are. */
    private const CONFIDENCE = 0.95;

    public function __construct(
        private readonly DatasetFileReader $reader,
        private readonly ColumnKeyGenerator $keys,
    ) {}

    /**
     * @return list<array{key: string, label: string, type: string, samples: list<string>}>
     *
     * @throws DatasetImportException
     */
    public function analyze(string $path, string $type): array
    {
        $maxColumns = (int) config('datasets.max_columns');
        $sampleRows = (int) config('datasets.sample_rows');
        $header = null;
        $sample = [];

        $rows = $this->reader->rows($path, $type);

        foreach ($rows as $row) {
            if ($header === null) {
                $header = $this->header($row, $maxColumns);

                continue;
            }

            $sample[] = $row;

            if (count($sample) >= $sampleRows) {
                break;
            }
        }

        // Closes the file now rather than when the generator is collected.
        unset($rows);

        if ($header === null || $sample === []) {
            throw DatasetImportException::empty();
        }

        $keys = $this->keys->generate($header);
        $columns = [];

        foreach ($header as $index => $label) {
            $values = array_map(fn (array $row): mixed => $row[$index] ?? null, $sample);

            $columns[] = [
                'key' => $keys[$index],
                'label' => $label,
                'type' => $this->detectType($values)->value,
                'samples' => $this->samples($values),
            ];
        }

        return $columns;
    }

    /**
     * @param  list<mixed>  $row
     * @return list<string> the header labels, one per column, blank ones named
     */
    private function header(array $row, int $maxColumns): array
    {
        $labels = array_map(fn (mixed $cell): string => (string) (ValueParser::string($cell) ?? ''), $row);

        while ($labels !== [] && end($labels) === '') {
            array_pop($labels);
        }

        if ($labels === []) {
            throw DatasetImportException::empty();
        }

        if (count($labels) > $maxColumns) {
            throw DatasetImportException::tooManyColumns($maxColumns);
        }

        return array_map(
            fn (string $label, int $index): string => $label === '' ? 'Columna '.($index + 1) : mb_substr($label, 0, 255),
            $labels,
            array_keys($labels),
        );
    }

    /**
     * @param  list<mixed>  $values
     */
    private function detectType(array $values): DatasetColumnType
    {
        $filled = array_values(array_filter($values, fn (mixed $value): bool => ! ValueParser::isEmpty($value)));
        $count = count($filled);

        if ($count === 0) {
            return DatasetColumnType::String;
        }

        $numbers = count(array_filter($filled, fn (mixed $value): bool => ValueParser::numeric($value) !== null));

        if ($numbers / $count >= self::CONFIDENCE) {
            return DatasetColumnType::Numeric;
        }

        $dates = count(array_filter($filled, fn (mixed $value): bool => ValueParser::datetime($value) !== null));

        return $dates / $count >= self::CONFIDENCE ? DatasetColumnType::Datetime : DatasetColumnType::String;
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    private function samples(array $values): array
    {
        $limit = (int) config('datasets.preview_rows');
        $samples = [];

        foreach ($values as $value) {
            $text = ValueParser::string($value);

            if ($text !== null && ! in_array($text, $samples, true)) {
                $samples[] = mb_substr($text, 0, 60);
            }

            if (count($samples) >= $limit) {
                break;
            }
        }

        return $samples;
    }
}
