<?php

namespace App\Domain\Dashboards\Models;

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable snapshot of a dashboard's spec. Reached only through its
 * dashboard, whose Policy governs access.
 */
class DashboardVersion extends Model
{
    use HasUuids;

    public const SOURCE_GENERATED = 'generated';

    public const SOURCE_REFINED = 'refined';

    public const SOURCE_RESTORED = 'restored';

    protected $fillable = [
        'tenant_id',
        'dashboard_id',
        'number',
        'source',
        'instruction',
        'spec_version',
        'spec',
        'created_by',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Dashboard, $this>
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'spec' => 'array',
        ];
    }
}
