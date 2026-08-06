<?php

namespace App\Domain\Dashboards\Policies;

use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;

class DashboardPolicy
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function viewAny(User $user): bool
    {
        $tenant = $this->currentTenant->get();

        return $tenant !== null && $this->membershipRole($user, $tenant) !== null;
    }

    public function view(User $user, Dashboard $dashboard): bool
    {
        $tenant = $this->currentTenant->get();

        return $tenant !== null
            && $dashboard->tenant_id === $tenant->getKey()
            && $this->membershipRole($user, $tenant) !== null;
    }

    public function create(User $user): bool
    {
        return $this->hasManagementRole($user);
    }

    public function update(User $user, Dashboard $dashboard): bool
    {
        return $this->view($user, $dashboard) && $this->hasManagementRole($user);
    }

    public function delete(User $user, Dashboard $dashboard): bool
    {
        return $this->update($user, $dashboard);
    }

    private function hasManagementRole(User $user): bool
    {
        $tenant = $this->currentTenant->get();

        if ($tenant === null) {
            return false;
        }

        return in_array($this->membershipRole($user, $tenant), [
            TenantRole::Owner->value,
            TenantRole::Admin->value,
        ], true);
    }

    private function membershipRole(User $user, Tenant $tenant): ?string
    {
        $membership = $user->tenants()
            ->whereKey($tenant->getKey())
            ->first();

        return $membership?->pivot?->role;
    }
}
