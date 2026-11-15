<?php

namespace App\Domain\Dashboards\Support;

use App\Domain\Ai\Catalogs\DatasetCatalog;
use App\Domain\Ai\Catalogs\DemoSalesDatasetCatalog;
use App\Domain\Ai\Catalogs\UploadedDatasetCatalog;
use App\Domain\Ai\Exceptions\InvalidDashboardSpecException;
use App\Domain\Dashboards\Enums\DatasetStatus;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\Dataset;

/**
 * Finds the catalog for a dataset key (what a DashboardSpec's `dataset` field holds).
 *
 * Uploaded datasets are looked up through the tenant-scoped model, so a key that
 * belongs to another organization resolves to nothing, exactly like a key that does
 * not exist. There must be a current tenant: with none, nothing resolves.
 */
final class DatasetCatalogResolver
{
    public function forKey(string $key): DatasetCatalog
    {
        if ($key === DemoSalesDatasetCatalog::DATASET) {
            return new DemoSalesDatasetCatalog;
        }

        $id = Dataset::idFromSpecKey($key);
        $dataset = $id === null ? null : Dataset::query()->with('columns')->find($id);

        if (! $dataset instanceof Dataset || $dataset->status !== DatasetStatus::Ready) {
            throw new InvalidDashboardSpecException('$.dataset is not available in the approved dataset catalog.');
        }

        return $this->forDataset($dataset);
    }

    public function forDataset(Dataset $dataset): UploadedDatasetCatalog
    {
        $columns = $dataset->columns
            ->filter(fn ($column): bool => $column->included && $column->slot !== null)
            ->map(fn ($column): array => [
                'key' => $column->key,
                'label' => $column->label,
                'type' => $column->type->value,
                'slot' => $column->slot,
            ])
            ->values()
            ->all();

        return new UploadedDatasetCatalog((string) $dataset->getKey(), $dataset->specKey(), $columns);
    }

    /**
     * The catalog a dashboard is built on: its dataset, or the demo data when it has none.
     */
    public function forDashboard(Dashboard $dashboard): DatasetCatalog
    {
        if ($dashboard->dataset_id === null) {
            return new DemoSalesDatasetCatalog;
        }

        $dataset = Dataset::query()->with('columns')->find($dashboard->dataset_id);

        if (! $dataset instanceof Dataset || $dataset->status !== DatasetStatus::Ready) {
            throw new InvalidDashboardSpecException('$.dataset is not available in the approved dataset catalog.');
        }

        return $this->forDataset($dataset);
    }
}
