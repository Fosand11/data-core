<?php

namespace App\Domain\Ai\Catalogs;

final class DemoSalesDatasetCatalog
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

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        return self::FIELDS;
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
}
