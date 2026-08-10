<?php

namespace App\Domain\Dashboards\Models;

use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Database\Factories\DashboardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dashboard extends Model
{
    /** @use HasFactory<DashboardFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'created_by',
        'title',
        'description',
        'original_prompt',
        'status',
        'spec_version',
        'dashboard_spec',
        'provider',
        'error_message',
        'generation_started_at',
        'generation_completed_at',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DashboardGeneration, $this>
     */
    public function generations(): HasMany
    {
        return $this->hasMany(DashboardGeneration::class);
    }

    /**
     * @param  Builder<Dashboard>  $query
     * @return Builder<Dashboard>
     */
    public function scopeForTenant(Builder $query, Tenant|string $tenant): Builder
    {
        return $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    protected static function newFactory(): Factory
    {
        return DashboardFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => DashboardStatus::class,
            'dashboard_spec' => 'array',
            'generation_started_at' => 'datetime',
            'generation_completed_at' => 'datetime',
        ];
    }
}
