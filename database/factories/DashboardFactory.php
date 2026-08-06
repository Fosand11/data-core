<?php

namespace Database\Factories;

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
            'name' => fake()->sentence(3),
        ];
    }
}
