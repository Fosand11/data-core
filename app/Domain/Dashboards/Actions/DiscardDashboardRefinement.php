<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DiscardDashboardRefinement
{
    public function execute(User $user, Dashboard $dashboard, DashboardGeneration $refinement): void
    {
        Gate::forUser($user)->authorize('refine', $dashboard);

        if ($refinement->dashboard_id !== $dashboard->getKey() || ! $refinement->isRefinement() || $refinement->applied_at !== null) {
            throw ValidationException::withMessages([
                'refinement' => 'Este ajuste ya no está disponible.',
            ]);
        }

        // A run that is still in flight is dropped by the job when it finishes.
        $refinement->update(['discarded_at' => $refinement->discarded_at ?? now()]);
    }
}
