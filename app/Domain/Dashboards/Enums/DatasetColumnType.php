<?php

namespace App\Domain\Dashboards\Enums;

/**
 * The types a dataset column can have. The values are the ones the dashboard
 * catalog already uses ("string", "numeric", "datetime").
 */
enum DatasetColumnType: string
{
    case String = 'string';
    case Numeric = 'numeric';
    case Datetime = 'datetime';

    /**
     * The prefix of the storage slots for this type (s1.., n1.., d1..).
     */
    public function slotPrefix(): string
    {
        return match ($this) {
            self::String => 's',
            self::Numeric => 'n',
            self::Datetime => 'd',
        };
    }
}
