<?php

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Models\Tenant;
use Closure;

/**
 * Runs work "as" a tenant outside an HTTP request (queued jobs, console commands),
 * where the middleware that normally resolves CurrentTenant has not run.
 */
final class TenantContext
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * Sets the tenant for the rest of the process/job. Queue workers reset scoped
     * bindings between jobs, so it never leaks into the next one.
     */
    public function enter(Tenant $tenant): void
    {
        $this->currentTenant->set($tenant);
    }

    /**
     * Runs the callback as the tenant and restores the previous context afterwards.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->currentTenant->get();
        $this->currentTenant->set($tenant);

        try {
            return $callback();
        } finally {
            $previous === null ? $this->currentTenant->forget() : $this->currentTenant->set($previous);
        }
    }
}
