<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Dashboards\Models\Dashboard;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class UpdateDashboardDetails
{
    public function execute(User $user, Dashboard $dashboard, string $title, ?string $description): Dashboard
    {
        Gate::forUser($user)->authorize('update', $dashboard);

        $description = $description === null ? null : trim($description);

        $dashboard->update([
            'title' => trim($title),
            'description' => $description === '' ? null : $description,
        ]);

        return $dashboard;
    }
}
