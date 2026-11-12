<?php

namespace Database\Factories;

use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemoSale>
 */
class DemoSaleFactory extends Factory
{
    protected $model = DemoSale::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 20, 500);

        return [
            'tenant_id' => Tenant::factory(),
            'sold_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'region' => fake()->randomElement(['Norte', 'Sur', 'Este', 'Oeste']),
            'product_id' => 'PRD-'.fake()->numerify('###'),
            'product_name' => fake()->words(2, true),
            'quantity' => $quantity,
            'total_amount' => round($unitPrice * $quantity, 2),
            'salesperson' => fake()->name(),
            'team' => fake()->randomElement(['Equipo Alfa', 'Equipo Beta']),
        ];
    }
}
