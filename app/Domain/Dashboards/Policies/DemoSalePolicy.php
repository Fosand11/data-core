<?php

namespace App\Domain\Dashboards\Policies;

use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Tenancy\CurrentTenant;
use App\Models\User;

class DemoSalePolicy
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentTenant($user);
    }

    public function view(User $user, DemoSale $sale): bool
    {
        return $this->isMemberOfCurrentTenant($user)
            && $sale->tenant_id === $this->currentTenant->id();
    }

    private function isMemberOfCurrentTenant(User $user): bool
    {
        $tenantId = $this->currentTenant->id();

        return $tenantId !== null
            && $user->tenants()->whereKey($tenantId)->exists();
    }
}
