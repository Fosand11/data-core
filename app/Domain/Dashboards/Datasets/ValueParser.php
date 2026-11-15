<?php

namespace App\Domain\Dashboards\Datasets;

use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeInterface;
use Throwable;

/**
 * Turns the raw cell values of an uploaded file into typed values. Used both to guess a
 * column's type and to import it, so the two always agree.
 */
final class ValueParser
{
    /** Largest magnitude a decimal(24,6) column can hold. */
    private const MAX_NUMERIC = 1.0E+17;

    public static function isEmpty(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * A number from a cell, or null when it is not clearly one. Handles currency
     * symbols, percentages, accounting negatives "(1,234.50)" and both "1,234.56" and
     * "1.234,56" styles. Values that look like identifiers (leading zeros, very long
     * digit strings, phone-like) are left as text on purpose.
     */
    public static function numeric(mixed $value): ?float
    {
        if (is_int($value)) {
            return (float) $value;
        }

        if (is_float($value)) {
            return is_finite($value) && abs($value) < self::MAX_NUMERIC ? $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        if ($text === '' || strlen($text) > 40) {
            return null;
        }

        $negative = preg_match('/^\((.*)\)$/', $text, $inner) === 1;
        $text = $negative ? $inner[1] : $text;
        $text = trim(str_replace(['$', '€', '£', '%', "\u{00A0}", ' '], '', $text));

        if ($text === '' || preg_match('/^[+-]?[0-9.,]+$/', $text) !== 1) {
            return null;
        }

        // IDs, ZIP codes and phone numbers must stay text: "007", "0123", 16+ digits.
        $digitsOnly = ltrim($text, '+-');
        if (preg_match('/^0\d+$/', $digitsOnly) === 1 || preg_match('/^\d{16,}$/', $digitsOnly) === 1) {
            return null;
        }

        $hasComma = str_contains($text, ',');
        $hasDot = str_contains($text, '.');

        if ($hasComma && $hasDot) {
            // The separator that comes last is the decimal one; the other groups thousands.
            $decimal = strrpos($text, ',') > strrpos($text, '.') ? ',' : '.';
            $thousands = $decimal === ',' ? '.' : ',';
            $text = str_replace($thousands, '', $text);
            $text = str_replace($decimal, '.', $text);
        } elseif ($hasComma) {
            // "1,234" / "1,234,567" are thousands; "12,5" is a decimal.
            $text = preg_match('/^[+-]?\d{1,3}(,\d{3})+$/', $text) === 1 ? str_replace(',', '', $text) : str_replace(',', '.', $text);
        } elseif ($hasDot && preg_match('/^[+-]?\d{1,3}(\.\d{3}){2,}$/', $text) === 1) {
            $text = str_replace('.', '', $text);
        }

        if (preg_match('/^[+-]?(\d+\.?\d*|\.\d+)$/', $text) !== 1) {
            return null;
        }

        $number = (float) $text;

        if (! is_finite($number) || abs($number) >= self::MAX_NUMERIC) {
            return null;
        }

        return $negative ? -abs($number) : $number;
    }

    /**
     * A moment from a cell, or null. Accepts the native date cells of an Excel file and
     * ISO strings; day-first dates ("31/12/2025") are read as day/month/year.
     */
    public static function datetime(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return self::plausible(CarbonImmutable::instance($value));
        }

        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        if ($text === '' || strlen($text) > 40) {
            return null;
        }

        try {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})([ T].*)?$/', $text) === 1) {
                return self::plausible(CarbonImmutable::parse($text));
            }

            if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$#', $text, $m) === 1) {
                $moment = CarbonImmutable::create((int) $m[3], (int) $m[2], (int) $m[1], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0), (int) ($m[6] ?? 0));

                // create() rolls 31/02 over to March: only accept dates that really exist.
                return $moment->day === (int) $m[1] && $moment->month === (int) $m[2] ? self::plausible($moment) : null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * The text of a cell, or null when empty. Capped to the column width.
     */
    public static function string(mixed $value): ?string
    {
        if (self::isEmpty($value)) {
            return null;
        }

        $text = match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof DateInterval => $value->format('%R%dd %hh %im'),
            is_bool($value) => $value ? 'Sí' : 'No',
            is_float($value) => rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.'),
            is_scalar($value) => (string) $value,
            default => '',
        };

        $text = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text));

        return $text === '' ? null : mb_substr($text, 0, 255);
    }

    private static function plausible(CarbonImmutable $moment): ?CarbonImmutable
    {
        return $moment->year >= 1900 && $moment->year <= 2100 ? $moment : null;
    }
}
