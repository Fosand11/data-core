<?php

namespace App\Http\Controllers;

use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): Response
    {
        $tenant = $currentTenant->get();
        $dashboards = collect();

        if ($tenant !== null) {
            Gate::authorize('viewAny', Dashboard::class);
            $dashboards = $tenant->dashboards()
                ->with('creator:id,name')
                ->latest()
                ->get()
                ->map(fn (Dashboard $dashboard): array => [
                    'id' => $dashboard->getKey(),
                    'title' => $dashboard->title,
                    'status' => $dashboard->status->value,
                    'created_at' => $dashboard->created_at->toIso8601String(),
                    'author' => $dashboard->creator === null ? null : [
                        'name' => $dashboard->creator->name,
                    ],
                ]);
        }

        $tenants = $request->user()
            ->tenants()
            ->where('tenants.status', TenantStatus::Active->value)
            ->orderBy('tenants.name')
            ->get()
            ->map(fn (Tenant $organization): array => [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'role' => $organization->pivot->role,
            ]);

        return Inertia::render('Dashboard', [
            'currentTenant' => $tenant === null ? null : [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'tenants' => $tenants,
            'dashboards' => $dashboards,
        ]);
    }
}
