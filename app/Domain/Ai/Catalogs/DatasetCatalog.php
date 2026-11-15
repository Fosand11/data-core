<?php

namespace App\Domain\Ai\Catalogs;

/**
 * The approved description of one dataset: which fields exist, their types, and which
 * physical column holds each. It is the single allowlist everything else trusts: the
 * prompt sent to the AI, the spec validator and the query executor.
 *
 * It never holds rows, only names and types, so it is safe to describe to a provider.
 */
interface DatasetCatalog
{
    /**
     * The value of a DashboardSpec's `dataset` field for this dataset.
     */
    public function dataset(): string;

    /**
     * @return array<string, string> field name => "string" | "numeric" | "datetime"
     */
    public function fields(): array;

    /**
     * Human-readable names for fields whose name alone says little (an uploaded
     * file's original headers). Empty when the field names are self-explanatory.
     *
     * @return array<string, string> field name => label
     */
    public function labels(): array;

    public function hasField(string $field): bool;

    public function typeOf(string $field): ?string;

    /**
     * The field a period filter applies to, or null when the dataset has no date.
     */
    public function dateField(): ?string;

    /**
     * The physical column behind a field. Only ever a value from this catalog's own
     * allowlist; never anything derived from user input.
     */
    public function column(string $field): string;
}
