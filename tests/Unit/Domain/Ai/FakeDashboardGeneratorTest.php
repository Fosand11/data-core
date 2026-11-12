<?php

namespace Tests\Unit\Domain\Ai;

use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Data\DashboardSpec;
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
            $this->assertSame('1.1', $spec->toArray()['version']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function baseSpec(): array
    {
        return (new FakeDashboardGenerator)
            ->generate(new DashboardGenerationInput('tenant-id', 1, 'ventas por region'))
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function refine(string $instruction): array
    {
        return (new FakeDashboardGenerator)
            ->generate(new DashboardGenerationInput('tenant-id', 1, $instruction, $this->baseSpec()))
            ->toArray();
    }

    public function test_refining_converts_the_first_chart_to_the_last_type_mentioned(): void
    {
        $refined = $this->refine('Cambia las barras por un gráfico circular');
        $charts = array_values(array_filter($refined['visualizations'], fn (array $v): bool => in_array($v['type'], ['bar', 'line', 'area', 'pie'], true)));

        $this->assertSame('pie', $charts[0]['type']);
        $this->assertSame(['category' => 'region', 'value' => 'total_sales'], $charts[0]['display']);
        $this->assertLessThanOrEqual(6, $charts[0]['query']['limit']);

        $line = $this->refine('muéstralo como una línea');
        $this->assertContains('line', array_column($line['visualizations'], 'type'));
    }

    public function test_refining_adds_a_detail_table_below_everything_once(): void
    {
        $refined = $this->refine('Agrega una tabla de detalle');
        $table = collect($refined['visualizations'])->firstWhere('key', 'detail_table');

        $this->assertNotNull($table);
        $this->assertSame('table', $table['type']);
        $this->assertSame(7, $table['layout']['y']);
        $this->assertSame(['region', 'total_sales'], $table['display']['columns']);

        $twice = (new FakeDashboardGenerator)
            ->generate(new DashboardGenerationInput('tenant-id', 1, 'agrega una tabla', $refined))
            ->toArray();
        $this->assertSame(count($refined['visualizations']), count($twice['visualizations']));
    }

    public function test_refining_can_remove_the_global_filters(): void
    {
        $this->assertNotSame([], $this->baseSpec()['global_filters']);
        $this->assertSame([], $this->refine('quita los filtros')['global_filters']);
    }

    public function test_an_instruction_the_fake_does_not_understand_returns_the_spec_unchanged(): void
    {
        $this->assertEquals($this->baseSpec(), $this->refine('hazlo más bonito'));
    }

    public function test_every_refinement_the_fake_can_produce_is_valid(): void
    {
        $validator = new DashboardSpecValidator;

        foreach (['cambia las barras por un gráfico circular', 'muéstralo como área', 'agrega una tabla de detalle', 'quita los filtros y agrega una tabla', 'cambia a líneas'] as $instruction) {
            $spec = DashboardSpec::fromArray($this->refine($instruction));

            $this->assertSame($spec, $validator->validate($spec), $instruction);
        }
    }
}
