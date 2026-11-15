<?php

namespace App\Domain\Dashboards\Jobs\Concerns;

use App\Domain\Dashboards\Models\DashboardGeneration;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;

/**
 * A queued job carries only a generation id and has no request, so no tenant is in
 * context. This finds the generation's own tenant and enters it; from then on every
 * tenant-owned query in the job is restricted to that tenant.
 */
trait EntersGenerationTenant
{
    private function enterGenerationTenant(): bool
    {
        // The one place that has to look without a tenant: it is how the tenant is found.
        $tenantId = DashboardGeneration::withoutTenantScope()->whereKey($this->generationId)->value('tenant_id');

        if (! is_string($tenantId)) {
            return false;
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant) {
            return false;
        }

        app(TenantContext::class)->enter($tenant);

        return true;
    }
}
