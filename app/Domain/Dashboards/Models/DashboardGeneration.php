<?php

namespace App\Domain\Dashboards\Models;

use App\Domain\Dashboards\Enums\DashboardGenerationKind;
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
        'kind',
        'base_version',
        'prompt',
        'spec_version',
        'validated_spec',
        'error_code',
        'error_message',
        'started_at',
        'completed_at',
        'applied_at',
        'discarded_at',
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

    public function isRefinement(): bool
    {
        return $this->kind === DashboardGenerationKind::Refine;
    }

    /**
     * Refinements the user has not resolved yet: still running, waiting for a
     * decision, or failed and not dismissed.
     *
     * @param  Builder<DashboardGeneration>  $query
     * @return Builder<DashboardGeneration>
     */
    public function scopeUnresolvedRefinements(Builder $query): Builder
    {
        return $query->where('kind', DashboardGenerationKind::Refine->value)
            ->whereNull('applied_at')
            ->whereNull('discarded_at');
    }

    protected function casts(): array
    {
        return [
            'status' => DashboardGenerationStatus::class,
            'kind' => DashboardGenerationKind::class,
            'base_version' => 'integer',
            'validated_spec' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'applied_at' => 'datetime',
            'discarded_at' => 'datetime',
        ];
    }
}
