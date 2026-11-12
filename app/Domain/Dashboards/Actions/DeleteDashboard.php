<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Dashboards\Models\Dashboard;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class DeleteDashboard
{
    /**
     * Generations and pins go with it through their foreign keys.
     */
    public function execute(User $user, Dashboard $dashboard): void
    {
        Gate::forUser($user)->authorize('delete', $dashboard);

        $dashboard->delete();
    }
}
