<?php

namespace App\Domain\Dashboards\Models;

use App\Domain\Dashboards\Enums\DatasetColumnType;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One column of an uploaded dataset: its dashboard-facing key, the original header,
 * its type and the storage slot it is mapped onto. Reached through its dataset,
 * whose Policy governs access.
 */
class DatasetColumn extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'dataset_id',
        'position',
        'key',
        'label',
        'type',
        'slot',
        'included',
        'samples',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'type' => DatasetColumnType::class,
            'included' => 'boolean',
            'samples' => 'array',
        ];
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
