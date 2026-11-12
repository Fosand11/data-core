<?php

namespace App\Domain\Dashboards\Models;

use App\Domain\Tenancy\Models\Tenant;
use Database\Factories\DemoSaleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-scoped demonstration dataset ("demo_sales") queried by dashboards.
 */
class DemoSale extends Model
{
    /** @use HasFactory<DemoSaleFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'sold_at',
        'region',
        'product_id',
        'product_name',
        'quantity',
        'total_amount',
        'salesperson',
        'team',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @param  Builder<DemoSale>  $query
     * @return Builder<DemoSale>
     */
    public function scopeForTenant(Builder $query, Tenant|string $tenant): Builder
    {
        return $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    protected static function newFactory(): Factory
    {
        return DemoSaleFactory::new();
    }

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'quantity' => 'integer',
            'total_amount' => 'decimal:2',
        ];
    }
}
