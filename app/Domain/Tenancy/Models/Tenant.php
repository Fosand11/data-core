<?php

namespace App\Domain\Tenancy\Models;

use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Dashboard, $this>
     */
    public function dashboards(): HasMany
    {
        return $this->hasMany(Dashboard::class);
    }

    /**
     * @return HasMany<DashboardGeneration, $this>
     */
    public function dashboardGenerations(): HasMany
    {
        return $this->hasMany(DashboardGeneration::class);
    }

    /**
     * @return HasMany<DemoSale, $this>
     */
    public function demoSales(): HasMany
    {
        return $this->hasMany(DemoSale::class);
    }

    protected static function newFactory(): Factory
    {
        return TenantFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
        ];
    }

    public function attachUser(User $user, TenantRole $role): void
    {
        $this->users()->syncWithoutDetaching([
            $user->getKey() => ['role' => $role->value],
        ]);
    }
}
