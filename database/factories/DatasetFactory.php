<?php

namespace Database\Factories;

use App\Domain\Dashboards\Enums\DatasetStatus;
use App\Domain\Dashboards\Models\Dataset;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dataset>
 */
class DatasetFactory extends Factory
{
    protected $model = Dataset::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by' => null,
            'name' => fake()->words(2, true),
            'status' => DatasetStatus::Ready,
            'source_filename' => 'ventas.csv',
            'source_type' => 'csv',
            'source_path' => null,
            'row_count' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => DatasetStatus::Pending]);
    }
}
