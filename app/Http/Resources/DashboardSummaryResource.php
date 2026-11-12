<?php

namespace App\Http\Resources;

use App\Domain\Dashboards\Models\Dashboard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight, display-oriented representation of a dashboard for lists, cards
 * and search results. It never includes the full spec or any query results.
 *
 * @mixin Dashboard
 */
final class DashboardSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'is_pinned' => (bool) $this->getAttribute('is_pinned'),
            'created_at' => $this->created_at->toIso8601String(),
            'author' => $this->relationLoaded('creator') && $this->creator !== null
                ? ['name' => $this->creator->name]
                : null,
            'visualization_types' => $this->visualizationTypes(),
        ];
    }

    /**
     * Distinct visualization types in the order they appear, e.g. ["kpi", "bar"].
     *
     * @return list<string>
     */
    private function visualizationTypes(): array
    {
        $visualizations = is_array($this->dashboard_spec) ? ($this->dashboard_spec['visualizations'] ?? []) : [];

        if (! is_array($visualizations)) {
            return [];
        }

        $types = [];

        foreach ($visualizations as $visualization) {
            if (is_array($visualization) && is_string($visualization['type'] ?? null)) {
                $types[$visualization['type']] = true;
            }
        }

        return array_keys($types);
    }
}
