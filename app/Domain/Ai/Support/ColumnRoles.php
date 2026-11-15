<?php

namespace App\Domain\Ai\Support;

use App\Domain\Ai\Catalogs\DatasetCatalog;
use Illuminate\Support\Str;

/**
 * Which of a dataset's columns make useful axes and measures, judged only from their names
 * and types (never from rows). Shared by the deterministic provider and the assistant's
 * suggestions, so both propose the same sensible things.
 */
final class ColumnRoles
{
    /**
     * An identifier, a name or a contact detail has a different value on almost every row, so
     * grouping a chart by it draws hundreds of one-row categories.
     */
    public static function isIdentifying(string $field): bool
    {
        return preg_match('/(^|_)(id|codigo|cod|correo|email|mail|nombre|name|telefono|tel|phone|direccion|address|url|folio)($|_)/i', Str::ascii($field)) === 1;
    }

    /**
     * Fields of a type, the useful ones first (the order inside each group is kept).
     *
     * @return list<string>
     */
    public static function byUsefulness(DatasetCatalog $catalog, string $type): array
    {
        $fields = array_keys(array_filter($catalog->fields(), fn (string $fieldType): bool => $fieldType === $type));
        usort($fields, fn (string $a, string $b): int => self::isIdentifying($a) <=> self::isIdentifying($b));

        return $fields;
    }

    /**
     * Text columns worth grouping by (identifying ones left out, unless nothing else exists).
     *
     * @return list<string>
     */
    public static function groupable(DatasetCatalog $catalog): array
    {
        $texts = self::byUsefulness($catalog, 'string');
        $useful = array_values(array_filter($texts, fn (string $field): bool => ! self::isIdentifying($field)));

        return $useful !== [] ? $useful : $texts;
    }

    /**
     * Numeric columns worth measuring (identifying ones left out, unless nothing else exists).
     *
     * @return list<string>
     */
    public static function measurable(DatasetCatalog $catalog): array
    {
        $numbers = self::byUsefulness($catalog, 'numeric');
        $useful = array_values(array_filter($numbers, fn (string $field): bool => ! self::isIdentifying($field)));

        return $useful !== [] ? $useful : $numbers;
    }
}
