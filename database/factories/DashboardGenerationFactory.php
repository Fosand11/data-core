<?php

namespace Database\Factories;

use App\Domain\Dashboards\Enums\DashboardGenerationStatus;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DashboardGeneration>
 */
class DashboardGenerationFactory extends Factory
{
    protected $model = DashboardGeneration::class;

    public function configure(): static
    {
        return $this->afterMaking(function (DashboardGeneration $generation): void {
            if ($generation->tenant_id === null && $generation->dashboard_id !== null) {
                $generation->tenant_id = Dashboard::query()->findOrFail($generation->dashboard_id)->tenant_id;
            }
        });
    }

    public function definition(): array
    {
        return [
            'dashboard_id' => Dashboard::factory(),
            'tenant_id' => null,
            'requested_by' => null,
            'provider' => 'fake',
            'status' => DashboardGenerationStatus::Queued,
            'prompt' => fake()->sentence(),
        ];
    }
}
