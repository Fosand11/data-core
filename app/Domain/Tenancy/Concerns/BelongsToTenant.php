<?php

namespace App\Domain\Tenancy\Concerns;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * For models that carry a `tenant_id`: scopes every query to the current tenant
 * and protects the column itself.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        // A request can never create a record that belongs to another organization,
        // whatever a controller or action passes in.
        static::creating(function (Model $model): void {
            $current = app(CurrentTenant::class)->id();

            if ($current !== null && $model->getAttribute('tenant_id') !== null && $model->getAttribute('tenant_id') !== $current) {
                throw new LogicException('Cannot create a record for a tenant other than the current one.');
            }
        });

        // A record never changes owner.
        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw new LogicException('tenant_id cannot be changed once a record exists.');
            }
        });
    }

    /**
     * An explicit, greppable opt-out for code that must look across tenants or that
     * has not entered one yet (for example, to find which tenant a queued job
     * belongs to). Keep its use rare and always re-check the tenant afterwards.
     *
     * @return Builder<static>
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}
