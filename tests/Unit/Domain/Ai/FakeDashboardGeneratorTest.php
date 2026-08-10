<?php

namespace Tests\Unit\Domain\Ai;

use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Providers\FakeDashboardGenerator;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use PHPUnit\Framework\TestCase;

class FakeDashboardGeneratorTest extends TestCase
{
    public function test_fake_generator_is_deterministic(): void
    {
        $generator = new FakeDashboardGenerator;
        $input = new DashboardGenerationInput('tenant-id', 1, 'Muéstrame las ventas por región del último trimestre.');

        $first = $generator->generate($input)->toArray();
        $second = $generator->generate($input)->toArray();

        $this->assertSame($first, $second);
        $this->assertSame('Ventas por región', $first['title']);
    }

    public function test_all_documented_demo_prompts_produce_valid_specs(): void
    {
        $generator = new FakeDashboardGenerator;
        $validator = new DashboardSpecValidator;
        $prompts = [
            'Muéstrame las ventas por región del último trimestre.',
            'Comparar ingresos con el periodo anterior.',
            'Identifica productos con ventas disminuidas.',
            'Resume el rendimiento del equipo comercial.',
            'Una solicitud genérica que no coincide con una plantilla.',
        ];

        foreach ($prompts as $prompt) {
            $spec = $generator->generate(new DashboardGenerationInput('tenant-id', 1, $prompt));

            $this->assertSame($spec, $validator->validate($spec));
            $this->assertSame('1.0', $spec->toArray()['version']);
        }
    }
}
