<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Http\Request;

/**
 * The props every organization-scoped page shares: the current organization and the
 * ones the user can switch to.
 */
trait SharesNavigationProps
{
    /**
     * @return array<string, mixed>
     */
    protected function navigationProps(Request $request, Tenant $tenant): array
    {
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

        return [
            'currentTenant' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'tenants' => $tenants,
        ];
    }
}
