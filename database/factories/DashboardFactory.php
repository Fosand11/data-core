<?php

namespace Database\Factories;

use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dashboard>
 */
class DashboardFactory extends Factory
{
    protected $model = Dashboard::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'original_prompt' => fake()->sentence(),
            'status' => DashboardStatus::Queued,
            'spec_version' => null,
            'dashboard_spec' => null,
            'provider' => 'fake',
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (): array => [
            'status' => DashboardStatus::Queued,
            'spec_version' => null,
            'dashboard_spec' => null,
            'provider' => 'fake',
        ]);
    }
}
