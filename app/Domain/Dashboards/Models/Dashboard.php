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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
     * @return HasMany<DashboardVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DashboardVersion::class)->orderBy('number');
    }

    /**
     * A generation can be run again when it failed, or when it is still
     * queued/generating but has not moved for a while (an interrupted worker).
     * Requires the original prompt, which legacy dashboards may lack.
     */
    public function canBeRetried(): bool
    {
        if (trim((string) $this->original_prompt) === '') {
            return false;
        }

        if ($this->status === DashboardStatus::Failed) {
            return true;
        }

        $staleAfter = max(1, (int) config('dashboard-ai.stale_after_minutes', 10));

        return in_array($this->status, [DashboardStatus::Queued, DashboardStatus::Generating], true)
            && $this->updated_at->lt(now()->subMinutes($staleAfter));
    }

    /**
     * Users who pinned this dashboard (personal favourites).
     *
     * @return BelongsToMany<User, $this>
     */
    public function pinnedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'dashboard_pins')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    /**
     * Adds an `is_pinned` flag computed for the given user.
     *
     * @param  Builder<Dashboard>  $query
     * @return Builder<Dashboard>
     */
    public function scopeWithPinnedState(Builder $query, User $user): Builder
    {
        return $query->withExists(['pinnedBy as is_pinned' => fn (Builder $pins) => $pins->where('users.id', $user->getKey())]);
    }

    /**
     * @param  Builder<Dashboard>  $query
     * @return Builder<Dashboard>
     */
    public function scopeForTenant(Builder $query, Tenant|string $tenant): Builder
    {
        return $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    /**
     * Case-insensitive match on title or original prompt. The term is bound as a
     * parameter and LIKE wildcards in it are escaped, so "%" and "_" are literal.
     *
     * @param  Builder<Dashboard>  $query
     * @return Builder<Dashboard>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($term))).'%';

        return $query->where(fn (Builder $inner): Builder => $inner
            ->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$needle])
            ->orWhereRaw("LOWER(original_prompt) LIKE ? ESCAPE '!'", [$needle]));
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
