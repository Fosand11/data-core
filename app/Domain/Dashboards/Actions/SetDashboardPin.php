<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Dashboards\Models\Dashboard;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class SetDashboardPin
{
    /**
     * Favourites are personal: pinning never changes what other members see.
     */
    public function execute(User $user, Dashboard $dashboard, bool $pinned): void
    {
        Gate::forUser($user)->authorize('pin', $dashboard);

        if ($pinned) {
            $dashboard->pinnedBy()->syncWithoutDetaching([
                $user->getKey() => ['tenant_id' => $dashboard->tenant_id],
            ]);

            return;
        }

        $dashboard->pinnedBy()->detach($user->getKey());
    }
}
