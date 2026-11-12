<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Fills a tenant with a deterministic, realistic-looking sales history covering
 * the last 12 months, so generated dashboards always have data to show.
 * The same tenant always receives the same pattern (seeded by tenant id).
 */
final class SeedDemoSales
{
    private const CHUNK_SIZE = 200;

    /**
     * id, name, unit price, base weight, trend slope (negative = declining).
     *
     * @var list<array{string, string, int, int, float}>
     */
    private const PRODUCTS = [
        ['PRD-001', 'Laptop Pro', 1200, 10, 1.0],
        ['PRD-002', 'Monitor 4K', 450, 14, 0.4],
        ['PRD-003', 'Teclado Mecánico', 110, 22, 0.0],
        ['PRD-004', 'Auriculares ANC', 220, 16, 0.6],
        ['PRD-005', 'Webcam HD', 90, 18, -1.3],
        ['PRD-006', 'Dock USB-C', 160, 12, -0.2],
        ['PRD-007', 'Silla Ergonómica', 380, 8, 0.2],
        ['PRD-008', 'Tablet Lite', 300, 10, -0.8],
    ];

    /**
     * @var array<string, int>
     */
    private const REGIONS = ['Norte' => 30, 'Sur' => 25, 'Este' => 25, 'Oeste' => 20];

    /**
     * @var list<array{string, string}>
     */
    private const SALESPEOPLE = [
        ['Ana Morales', 'Equipo Alfa'],
        ['Luis Herrera', 'Equipo Alfa'],
        ['Sofía Ramírez', 'Equipo Beta'],
        ['Diego Castillo', 'Equipo Beta'],
        ['Valeria Núñez', 'Equipo Gamma'],
        ['Mateo Fuentes', 'Equipo Gamma'],
    ];

    public function execute(Tenant $tenant, int $rows = 600): int
    {
        $random = new Randomizer(new Mt19937(crc32($tenant->getKey())));
        $now = Carbon::now();
        $batch = [];
        $inserted = 0;

        for ($index = 0; $index < $rows; $index++) {
            // Density grows towards the present, giving charts a visible trend.
            $daysAgo = (int) floor(365 * (1 - sqrt($this->unit($random))));
            $recency = 1 - ($daysAgo / 365);

            [$productId, $productName, $unitPrice] = $this->pickProduct($random, $recency);
            [$salesperson, $team] = self::SALESPEOPLE[$random->getInt(0, count(self::SALESPEOPLE) - 1)];

            $quantity = $random->getInt(1, 100) <= 70 ? $random->getInt(1, 2) : $random->getInt(3, 6);
            $discount = [0.0, 0.0, 0.05, 0.1][$random->getInt(0, 3)];
            $soldAt = $now->copy()->subSeconds(($daysAgo * 86400) + $random->getInt(0, 86399));

            $batch[] = [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->getKey(),
                'sold_at' => $soldAt,
                'region' => $this->pickRegion($random),
                'product_id' => $productId,
                'product_name' => $productName,
                'quantity' => $quantity,
                'total_amount' => round($unitPrice * $quantity * (1 - $discount), 2),
                'salesperson' => $salesperson,
                'team' => $team,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) === self::CHUNK_SIZE) {
                DemoSale::query()->insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DemoSale::query()->insert($batch);
            $inserted += count($batch);
        }

        return $inserted;
    }

    private function unit(Randomizer $random): float
    {
        return $random->getInt(0, 999_999) / 1_000_000;
    }

    /**
     * @return array{string, string, int}
     */
    private function pickProduct(Randomizer $random, float $recency): array
    {
        $weights = array_map(
            fn (array $product): float => max(1.0, $product[3] * (1 + $product[4] * ($recency - 0.5))),
            self::PRODUCTS,
        );

        $target = $this->unit($random) * array_sum($weights);

        foreach (self::PRODUCTS as $index => $product) {
            $target -= $weights[$index];

            if ($target <= 0) {
                return [$product[0], $product[1], $product[2]];
            }
        }

        $last = self::PRODUCTS[array_key_last(self::PRODUCTS)];

        return [$last[0], $last[1], $last[2]];
    }

    private function pickRegion(Randomizer $random): string
    {
        $target = $random->getInt(1, array_sum(self::REGIONS));

        foreach (self::REGIONS as $region => $weight) {
            $target -= $weight;

            if ($target <= 0) {
                return $region;
            }
        }

        return array_key_first(self::REGIONS);
    }
}
