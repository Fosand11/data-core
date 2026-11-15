<?php

namespace Tests\Unit\Domain\Dashboards\Datasets;

use App\Domain\Dashboards\Datasets\ColumnKeyGenerator;
use App\Domain\Dashboards\Datasets\ValueParser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValueParserTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: ?float}>
     */
    public static function numbers(): array
    {
        return [
            'integer' => ['42', 42.0],
            'decimal' => ['3.14', 3.14],
            'negative' => ['-7.5', -7.5],
            'us thousands' => ['1,234.56', 1234.56],
            'european thousands' => ['1.234,56', 1234.56],
            'comma decimal' => ['12,5', 12.5],
            'comma thousands only' => ['1,234', 1234.0],
            'two comma groups' => ['1,234,567', 1234567.0],
            'dot thousands only' => ['1.234.567', 1234567.0],
            'currency' => ['$1,200', 1200.0],
            'euro with spaces' => ['€ 2 500,75', 2500.75],
            'percent' => ['12%', 12.0],
            'accounting negative' => ['(500)', -500.0],
            'native int' => [7, 7.0],
            'native float' => [2.5, 2.5],
            'leading zeros are an id, not a number' => ['007', null],
            'zip code' => ['01234', null],
            'very long digit string is an id' => ['1234567890123456', null],
            'text' => ['abc', null],
            'mixed' => ['12abc', null],
            'exponent notation is not guessed' => ['1e5', null],
            'two decimals' => ['1.2.3', null],
            'empty' => ['', null],
            'null' => [null, null],
            'too large for the column' => ['1'.str_repeat('0', 20), null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_numbers_are_read_only_when_they_clearly_are_numbers(mixed $input, ?float $expected): void
    {
        $this->assertSame($expected, ValueParser::numeric($input));
    }

    /**
     * @return array<string, array{0: mixed, 1: ?string}>
     */
    public static function dates(): array
    {
        return [
            'iso date' => ['2025-12-31', '2025-12-31 00:00:00'],
            'iso datetime' => ['2025-12-31 14:30:05', '2025-12-31 14:30:05'],
            'iso with T' => ['2025-12-31T14:30:00', '2025-12-31 14:30:00'],
            'day first slashes' => ['31/12/2025', '2025-12-31 00:00:00'],
            'day first dashes' => ['5-3-2025', '2025-03-05 00:00:00'],
            'day first with time' => ['31/12/2025 18:45', '2025-12-31 18:45:00'],
            'ambiguous is read day first' => ['03/04/2025', '2025-04-03 00:00:00'],
            'native date cell' => [new DateTimeImmutable('2024-02-29 10:00:00'), '2024-02-29 10:00:00'],
            'a day that does not exist' => ['31/02/2025', null],
            'month 13' => ['2025-13-45', null],
            'implausible year' => ['1850-01-01', null],
            'text' => ['mañana', null],
            'a plain number' => ['2025', null],
            'empty' => ['  ', null],
        ];
    }

    #[DataProvider('dates')]
    public function test_dates_are_read_day_first_and_impossible_ones_are_rejected(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, ValueParser::datetime($input)?->format('Y-m-d H:i:s'));
    }

    public function test_text_is_trimmed_capped_and_stripped_of_control_characters(): void
    {
        $this->assertNull(ValueParser::string('   '));
        $this->assertNull(ValueParser::string(null));
        $this->assertSame('Norte', ValueParser::string("  Norte\x00 "));
        $this->assertSame(255, mb_strlen((string) ValueParser::string(str_repeat('ñ', 400))));
        $this->assertSame('Sí', ValueParser::string(true));
        $this->assertSame('1.5', ValueParser::string(1.5));
    }

    /**
     * @return array<string, array{0: list<string>, 1: list<string>}>
     */
    public static function headers(): array
    {
        return [
            'accents and spaces' => [['Monto Total', 'Región', 'Año de venta'], ['monto_total', 'region', 'ano_de_venta']],
            'starts with a digit' => [['2024 ventas'], ['c_2024_ventas']],
            'duplicates get a suffix' => [['Región', 'region', 'REGIÓN'], ['region', 'region_2', 'region_3']],
            'blank and symbol-only become numbered columns' => [['', '###', 'Ok'], ['columna_1', 'columna_2', 'ok']],
            'punctuation is dropped' => [['Precio ($)', 'Qty.'], ['precio', 'qty']],
        ];
    }

    /**
     * @param  list<string>  $labels
     * @param  list<string>  $expected
     */
    #[DataProvider('headers')]
    public function test_headers_become_safe_unique_identifiers(array $labels, array $expected): void
    {
        $keys = (new ColumnKeyGenerator)->generate($labels);

        $this->assertSame($expected, $keys);

        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key);
            $this->assertLessThanOrEqual(50, strlen($key));
        }
    }

    public function test_a_very_long_or_hostile_header_still_gives_a_short_safe_key(): void
    {
        $keys = (new ColumnKeyGenerator)->generate([
            str_repeat('a', 200),
            "ventas'; DROP TABLE users;--",
            '<script>alert(1)</script>',
        ]);

        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key);
            $this->assertLessThanOrEqual(50, strlen($key));
        }

        $this->assertCount(3, array_unique($keys));
    }
}
