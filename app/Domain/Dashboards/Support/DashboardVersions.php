<?php

namespace App\Domain\Dashboards\Support;

use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardVersion;

/**
 * Append-only history of a dashboard's spec. Callers hold a lock on the
 * dashboard row so version numbers are allocated one at a time.
 */
final class DashboardVersions
{
    /**
     * Number of the current version, or 0 when the dashboard has no history yet.
     */
    public function currentNumber(Dashboard $dashboard): int
    {
        return (int) $dashboard->versions()->reorder()->max('number');
    }

    /**
     * Dashboards created before versioning get their present spec recorded as
     * version 1, so there is always an original to go back to.
     */
    public function ensureInitial(Dashboard $dashboard): void
    {
        if (! is_array($dashboard->dashboard_spec) || $this->currentNumber($dashboard) > 0) {
            return;
        }

        $this->record($dashboard, $dashboard->dashboard_spec, DashboardVersion::SOURCE_GENERATED, null, $dashboard->created_by);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    public function record(Dashboard $dashboard, array $spec, string $source, ?string $instruction, ?int $userId): DashboardVersion
    {
        return $dashboard->versions()->create([
            'tenant_id' => $dashboard->tenant_id,
            'number' => $this->currentNumber($dashboard) + 1,
            'source' => $source,
            'instruction' => $instruction,
            'spec_version' => (string) ($spec['version'] ?? '1.1'),
            'spec' => $spec,
            'created_by' => $userId,
        ]);
    }

    /**
     * Makes `$spec` the live spec. The dashboard's own title and description
     * follow the spec only when the spec actually changed them, so a manual
     * rename is not undone by an unrelated change.
     *
     * @param  array<string, mixed>  $spec
     */
    public function publish(Dashboard $dashboard, array $spec): void
    {
        $previous = is_array($dashboard->dashboard_spec) ? $dashboard->dashboard_spec : [];
        $attributes = [
            'spec_version' => (string) ($spec['version'] ?? '1.1'),
            'dashboard_spec' => $spec,
        ];

        if (($spec['title'] ?? null) !== null && ($spec['title'] ?? null) !== ($previous['title'] ?? null)) {
            $attributes['title'] = $spec['title'];
        }

        if (array_key_exists('description', $spec) && ($spec['description'] ?? null) !== ($previous['description'] ?? null)) {
            $attributes['description'] = $spec['description'] === '' ? null : $spec['description'];
        }

        $dashboard->update($attributes);
    }
}
