<?php

namespace App\Domain\Dashboards\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of an uploaded dataset, stored in typed slots (s1.., n1.., d1..). The
 * dataset's columns say which slot holds which column. Dashboards query it through
 * the safe structured executor only.
 */
class DatasetRow extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    // Rows are written in bulk through the query builder, never mass-assigned. The two
    // keys are listed so the tenant-scope test can recognise this as a tenant-owned model.
    protected $fillable = ['tenant_id', 'dataset_id'];

    /**
     * @param  Builder<DatasetRow>  $query
     * @return Builder<DatasetRow>
     */
    public function scopeForTenant(Builder $query, Tenant|string $tenant): Builder
    {
        return $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Dataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
