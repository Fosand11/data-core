<?php

namespace App\Domain\Dashboards\Models;

use App\Domain\Dashboards\Enums\DatasetStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Database\Factories\DatasetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A table of the customer's own data (uploaded as CSV or Excel) that dashboards can
 * be built on. Tenant-scoped like every business resource.
 */
class Dataset extends Model
{
    /** @use HasFactory<DatasetFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /** The identifier a DashboardSpec uses for an uploaded dataset: "ds_" plus the id without dashes. */
    private const SPEC_KEY_PATTERN = '/^ds_([0-9a-f]{8})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{12})$/';

    protected $fillable = [
        'tenant_id',
        'created_by',
        'name',
        'status',
        'source_filename',
        'source_type',
        'source_path',
        'row_count',
        'warning_count',
        'error_code',
        'error_message',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DatasetStatus::class,
            'row_count' => 'integer',
            'warning_count' => 'integer',
            'imported_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DatasetColumn, $this>
     */
    public function columns(): HasMany
    {
        return $this->hasMany(DatasetColumn::class)->orderBy('position');
    }

    /**
     * @return HasMany<DatasetRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(DatasetRow::class);
    }

    /**
     * @return HasMany<Dashboard, $this>
     */
    public function dashboards(): HasMany
    {
        return $this->hasMany(Dashboard::class);
    }

    /**
     * @param  Builder<Dataset>  $query
     * @return Builder<Dataset>
     */
    public function scopeForTenant(Builder $query, Tenant|string $tenant): Builder
    {
        return $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    public function isReady(): bool
    {
        return $this->status === DatasetStatus::Ready;
    }

    /**
     * What a DashboardSpec calls this dataset in its `dataset` field.
     */
    public function specKey(): string
    {
        return 'ds_'.str_replace('-', '', (string) $this->getKey());
    }

    /**
     * The dataset id a spec key stands for, or null when it is not an uploaded-dataset key.
     */
    public static function idFromSpecKey(string $key): ?string
    {
        if (preg_match(self::SPEC_KEY_PATTERN, $key, $parts) !== 1) {
            return null;
        }

        return "{$parts[1]}-{$parts[2]}-{$parts[3]}-{$parts[4]}-{$parts[5]}";
    }

    protected static function newFactory(): Factory
    {
        return DatasetFactory::new();
    }
}
