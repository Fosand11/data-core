<?php

namespace App\Http\Controllers;

use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Resources\DashboardSummaryResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): Response
    {
        $tenant = $currentTenant->get();
        $dashboards = [];

        if ($tenant !== null) {
            Gate::authorize('viewAny', Dashboard::class);
            $dashboards = DashboardSummaryResource::collection(
                $tenant->dashboards()->withPinnedState($request->user())->with('creator:id,name')->latest()->limit(12)->get(),
            )->resolve();
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
