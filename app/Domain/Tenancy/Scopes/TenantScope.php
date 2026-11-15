<?php

namespace App\Domain\Tenancy\Scopes;

use App\Domain\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every read, update and delete on a tenant-owned model to the current
 * tenant, so isolation does not depend on each query remembering a filter.
 *
 * It fails closed: with no tenant in context nothing is visible, instead of
 * everything. Code that legitimately runs without one (a queued job, a console
 * command) must enter a tenant explicitly (see TenantContext) or opt out on purpose
 * with Model::withoutTenantScope().
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
