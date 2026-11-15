<?php

namespace Tests\Feature\Dashboards\Datasets;

use App\Domain\Dashboards\Datasets\ColumnKeyGenerator;
use App\Domain\Dashboards\Datasets\DatasetAnalyzer;
use App\Domain\Dashboards\Datasets\DatasetFileReader;
use App\Domain\Dashboards\Datasets\DatasetImportException;
use DateTimeImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class DatasetAnalyzerTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_a_plain_utf8_csv_is_understood(): void
    {
        $columns = $this->analyze("Región,Monto,Fecha,Nota\nNorte,\"1,200.50\",2025-01-15,\"dice \"\"hola\"\", y más\"\nSur,300,2025-02-20,\"dos\nlíneas\"\nNorte,75.25,2025-03-01,ok\n");

        $this->assertSame(['region', 'monto', 'fecha', 'nota'], array_column($columns, 'key'));
        $this->assertSame(['Región', 'Monto', 'Fecha', 'Nota'], array_column($columns, 'label'));
        $this->assertSame(['string', 'numeric', 'datetime', 'string'], array_column($columns, 'type'));
        $this->assertSame(['Norte', 'Sur'], $columns[0]['samples']);
        $this->assertSame('dice "hola", y más', $columns[3]['samples'][0]);
    }

    public function test_a_spanish_excel_csv_in_windows_1252_with_semicolons_is_understood(): void
    {
        $text = "Región;Año;Importe\nNorte;2024;1.234,56\nSur;2025;890,10\nEste;2025;45,00\n";
        $columns = $this->analyze((string) mb_convert_encoding($text, 'Windows-1252', 'UTF-8'));

        $this->assertSame(['Región', 'Año', 'Importe'], array_column($columns, 'label'), 'accents must survive the conversion');
        $this->assertSame(['region', 'ano', 'importe'], array_column($columns, 'key'));
        $this->assertSame(['string', 'numeric', 'numeric'], array_column($columns, 'type'));
        $this->assertSame(['Norte', 'Sur', 'Este'], $columns[0]['samples']);
    }

    public function test_a_utf8_byte_order_mark_does_not_end_up_in_the_first_header(): void
    {
        $columns = $this->analyze("\xEF\xBB\xBFVentas,Mes\n100,2025-01-01\n200,2025-02-01\n");

        $this->assertSame('Ventas', $columns[0]['label']);
        $this->assertSame('ventas', $columns[0]['key']);
    }

    public function test_a_utf16_csv_from_excel_is_understood(): void
    {
        $text = "Producto\tPrecio\nLápiz\t2.5\nCuaderno\t10\nGoma\t1.25\n";
        $columns = $this->analyze("\xFF\xFE".mb_convert_encoding($text, 'UTF-16LE', 'UTF-8'));

        $this->assertSame(['Producto', 'Precio'], array_column($columns, 'label'));
        $this->assertSame(['string', 'numeric'], array_column($columns, 'type'));
        $this->assertSame('Lápiz', $columns[0]['samples'][0]);
    }

    public function test_an_excel_file_reads_native_numbers_and_dates(): void
    {
        $path = $this->tempFile('xlsx');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Cliente', 'Ingreso', 'Alta']));

        // Excel stores a date as a number with a date format; write it the same way.
        $date = (new Style)->withFormat('yyyy-mm-dd');

        foreach ([['Ana', 1500.5, '2025-03-10'], ['Luis', 820, '2025-04-02'], ['Eva', 99.9, '2025-05-21']] as [$name, $income, $day]) {
            $writer->addRow(new Row([Cell::fromValue($name), Cell::fromValue($income), Cell::fromValue(new DateTimeImmutable($day), $date)]));
        }

        $writer->close();

        $columns = (new DatasetAnalyzer(new DatasetFileReader, new ColumnKeyGenerator))->analyze($path, 'xlsx');

        $this->assertSame(['cliente', 'ingreso', 'alta'], array_column($columns, 'key'));
        $this->assertSame(['string', 'numeric', 'datetime'], array_column($columns, 'type'));
        $this->assertSame(['Ana', 'Luis', 'Eva'], $columns[0]['samples']);
    }

    public function test_blank_lines_and_trailing_empty_columns_are_ignored_and_blank_headers_are_named(): void
    {
        $columns = $this->analyze("A,,C,,\n1,2,3,,\n\n4,5,6,,\n,,,,\n");

        $this->assertSame(['A', 'Columna 2', 'C'], array_column($columns, 'label'));
        $this->assertSame(['a', 'columna_2', 'c'], array_column($columns, 'key'));
    }

    public function test_a_column_is_numeric_only_when_almost_all_its_values_are(): void
    {
        $mixed = "Valor\n".implode("\n", array_merge(array_fill(0, 8, '10'), ['n/a', 'texto']))."\n"; // 80 %
        $nearly = "Valor\n".implode("\n", array_merge(array_fill(0, 99, '10'), ['n/a']))."\n"; // 99 %

        $this->assertSame('string', $this->analyze($mixed)[0]['type']);
        $this->assertSame('numeric', $this->analyze($nearly)[0]['type']);
    }

    public function test_identifier_like_columns_stay_text(): void
    {
        $columns = $this->analyze("Codigo,Telefono,Cantidad\n007,5512345678901234,3\n0123,5598765432109876,4\n0456,5511122233344455,5\n");

        $this->assertSame(['string', 'string', 'numeric'], array_column($columns, 'type'));
    }

    public function test_a_file_with_only_a_header_or_nothing_is_rejected_as_empty(): void
    {
        foreach (["Region,Monto\n", '', "\n\n\n", ",,,\n1,2,3\n"] as $content) {
            $this->assertReason('EMPTY_FILE', fn () => $this->analyze($content), 'content: '.json_encode($content));
        }
    }

    public function test_too_many_columns_are_rejected(): void
    {
        config(['datasets.max_columns' => 20]);
        $header = implode(',', array_map(fn (int $i): string => "c{$i}", range(1, 21)));
        $row = implode(',', range(1, 21));

        $this->assertReason('TOO_MANY_COLUMNS', fn () => $this->analyze("{$header}\n{$row}\n"));
    }

    public function test_a_damaged_excel_file_is_reported_as_unreadable_not_as_a_crash(): void
    {
        $path = $this->tempFile('xlsx');
        file_put_contents($path, random_bytes(2048));

        $this->assertReason('FILE_UNREADABLE', fn () => (new DatasetAnalyzer(new DatasetFileReader, new ColumnKeyGenerator))->analyze($path, 'xlsx'));

        // A text file renamed to .xlsx is not a zip either.
        file_put_contents($path, "Region,Monto\nNorte,1\n");
        $this->assertReason('FILE_UNREADABLE', fn () => (new DatasetAnalyzer(new DatasetFileReader, new ColumnKeyGenerator))->analyze($path, 'xlsx'));
    }

    public function test_the_reader_streams_rows_without_loading_the_whole_file(): void
    {
        $path = $this->tempFile('csv');
        $handle = fopen($path, 'wb');
        fwrite($handle, "id,valor\n");

        for ($i = 1; $i <= 30000; $i++) {
            fwrite($handle, "{$i},".($i * 2)."\n");
        }

        fclose($handle);

        $before = memory_get_usage();
        $count = 0;

        foreach ((new DatasetFileReader)->rows($path, 'csv') as $row) {
            $count++;
        }

        $this->assertSame(30001, $count);
        $this->assertLessThan(8 * 1024 * 1024, memory_get_peak_usage() - $before + 1, 'reading must not grow with the file size');
    }

    // --- helpers ----------------------------------------------------------------------

    /**
     * @return list<array{key: string, label: string, type: string, samples: list<string>}>
     */
    private function analyze(string $csv): array
    {
        $path = $this->tempFile('csv');
        file_put_contents($path, $csv);

        return (new DatasetAnalyzer(new DatasetFileReader, new ColumnKeyGenerator))->analyze($path, 'csv');
    }

    private function tempFile(string $extension): string
    {
        $path = sys_get_temp_dir().'/'.uniqid('analyzer_', true).'.'.$extension;
        $this->files[] = $path;

        return $path;
    }

    private function assertReason(string $reason, callable $action, string $message = ''): void
    {
        try {
            $action();
        } catch (DatasetImportException $exception) {
            $this->assertSame($reason, $exception->reason, $message);

            return;
        }

        $this->fail("Expected a {$reason} import error. {$message}");
    }
}
