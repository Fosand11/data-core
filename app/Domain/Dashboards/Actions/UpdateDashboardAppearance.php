<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Support\ChartPalettes;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Saves how a dashboard is drawn: its palette and, per chart, which of the palette's
 * six colours that chart uses. Only ids from the palette catalog and slot numbers are
 * accepted, so the stored value can never carry free-form colours or markup.
 */
final class UpdateDashboardAppearance
{
    /**
     * @param  array<string, int>  $colors  visualization key => palette slot (0-5)
     */
    public function execute(User $user, Dashboard $dashboard, string $palette, array $colors): Dashboard
    {
        Gate::forUser($user)->authorize('update', $dashboard);

        if (! ChartPalettes::has($palette)) {
            throw ValidationException::withMessages(['palette' => 'Esa paleta no existe.']);
        }

        // Colours of charts that are no longer in the dashboard (an AI change may remove
        // or replace them) are dropped instead of piling up.
        $keys = collect($dashboard->dashboard_spec['visualizations'] ?? [])->pluck('key')->filter(fn (mixed $key): bool => is_string($key))->all();
        $kept = [];

        foreach ($colors as $key => $slot) {
            if (in_array((string) $key, $keys, true) && is_int($slot) && $slot >= 0 && $slot < ChartPalettes::SLOTS) {
                $kept[(string) $key] = $slot;
            }
        }

        $isDefault = $palette === ChartPalettes::DEFAULT && $kept === [];

        $dashboard->update(['appearance' => $isDefault ? null : ['palette' => $palette, 'colors' => $kept]]);

        return $dashboard;
    }
}
