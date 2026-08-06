<?php

namespace App\Domain\Dashboards\Models;

use App\Domain\Tenancy\Models\Tenant;
use Database\Factories\DashboardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dashboard extends Model
{
    /** @use HasFactory<DashboardFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected static function newFactory(): Factory
    {
        return DashboardFactory::new();
    }
}
