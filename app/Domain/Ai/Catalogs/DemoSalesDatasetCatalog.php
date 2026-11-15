<?php

namespace App\Domain\Ai\Catalogs;

use LogicException;

final class DemoSalesDatasetCatalog implements DatasetCatalog
{
    public const DATASET = 'demo_sales';

    /**
     * @var array<string, string>
     */
    private const FIELDS = [
        'total_amount' => 'numeric',
        'region' => 'string',
        'sold_at' => 'datetime',
        'product_name' => 'string',
        'product_id' => 'string',
        'quantity' => 'numeric',
        'salesperson' => 'string',
        'team' => 'string',
    ];

    public function dataset(): string
    {
        return self::DATASET;
    }

    /**
     * @return array<string, string>]
     */
    public function fields(): array
    {
        return self::FIELDS;
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return [];
    }

    /**
     * The field a period filter applies to.
     */
    public function dateField(): string
    {
        return 'sold_at';
    }

    public function hasField(string $field): bool
    {
        return array_key_exists($field, self::FIELDS);
    }

    public function typeOf(string $field): ?string
    {
        return self::FIELDS[$field] ?? null;
    }

    /**
     * The demo table names its columns like its fields.
     */
    public function column(string $field): string
    {
        if (! $this->hasField($field)) {
            throw new LogicException('Field is not part of the approved dataset catalog.');
        }

        return $field;
    }
}
