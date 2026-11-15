<?php

namespace App\Domain\Ai\Catalogs;

use LogicException;

/**
 * The catalog of a dataset the customer uploaded: its columns' keys, types and the
 * storage slot behind each. Built from the dataset's own column records, so the
 * allowlist is exactly what was confirmed at import time.
 */
final class UploadedDatasetCatalog implements DatasetCatalog
{
    /**
     * @param  list<array{key: string, label: string, type: string, slot: string}>  $columns
     */
    public function __construct(
        private readonly string $datasetId,
        private readonly string $datasetKey,
        private readonly array $columns,
    ) {}

    /** The id of the Dataset record the rows belong to. */
    public function datasetId(): string
    {
        return $this->datasetId;
    }

    public function dataset(): string
    {
        return $this->datasetKey;
    }

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->columns as $column) {
            $fields[$column['key']] = $column['type'];
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        $labels = [];

        foreach ($this->columns as $column) {
            $labels[$column['key']] = $column['label'];
        }

        return $labels;
    }

    public function hasField(string $field): bool
    {
        return $this->find($field) !== null;
    }

    public function typeOf(string $field): ?string
    {
        return $this->find($field)['type'] ?? null;
    }

    /**
     * The first datetime column: the one a period filter applies to.
     */
    public function dateField(): ?string
    {
        foreach ($this->columns as $column) {
            if ($column['type'] === 'datetime') {
                return $column['key'];
            }
        }

        return null;
    }

    public function column(string $field): string
    {
        return $this->find($field)['slot'] ?? throw new LogicException('Field is not part of the approved dataset catalog.');
    }

    /**
     * @return array{key: string, label: string, type: string, slot: string}|null
     */
    private function find(string $field): ?array
    {
        foreach ($this->columns as $column) {
            if ($column['key'] === $field) {
                return $column;
            }
        }

        return null;
    }
}
