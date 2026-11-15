<?php

namespace App\Domain\Dashboards\Policies;

use App\Domain\Dashboards\Models\Dataset;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;

/**
 * Any member of the organization can see its datasets and ask questions about them;
 * only owners and administrators can upload or delete them.
 */
class DatasetPolicy
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function viewAny(User $user): bool
    {
        $tenant = $this->currentTenant->get();

        return $tenant !== null && $this->membershipRole($user, $tenant) !== null;
    }

    public function view(User $user, Dataset $dataset): bool
    {
        $tenant = $this->currentTenant->get();

        return $tenant !== null
            && $dataset->tenant_id === $tenant->getKey()
            && $this->membershipRole($user, $tenant) !== null;
    }

    public function create(User $user): bool
    {
        return $this->hasManagementRole($user);
    }

    public function update(User $user, Dataset $dataset): bool
    {
        return $this->view($user, $dataset) && $this->hasManagementRole($user);
    }

    public function delete(User $user, Dataset $dataset): bool
    {
        return $this->update($user, $dataset);
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
