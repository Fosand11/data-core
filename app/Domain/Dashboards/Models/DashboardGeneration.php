<?php

namespace App\Domain\Dashboards\Models;

use App\Domain\Dashboards\Enums\DashboardGenerationStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Database\Factories\DashboardGenerationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardGeneration extends Model
{
    /** @use HasFactory<DashboardGenerationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'dashboard_id',
        'requested_by',
        'provider',
        'model',
        'status',
        'prompt',
        'spec_version',
        'validated_spec',
        'error_code',
        'error_message',
        'started_at',
        'completed_at',
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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @param  Builder<DashboardGeneration>  $query
     * @return Builder<DashboardGeneration>
     */
    public function scopeForTenant(Builder $query, Tenant|string $tenant): Builder
    {
        return $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    protected static function newFactory(): Factory
    {
        return DashboardGenerationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => DashboardGenerationStatus::class,
            'validated_spec' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
