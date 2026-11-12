<?php

namespace Tests\Unit\Domain\Ai;

use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Providers\FakeDashboardGenerator;
use App\Domain\Ai\Schemas\DashboardSpecSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardSpecSchemaTest extends TestCase
{
    /**
     * Keywords Gemini does not document, or that inflate the schema it compiles.
     */
    private const GEMINI_UNSUPPORTED = [
        '$ref', '$defs', '$schema', '$id', 'const', 'oneOf', 'allOf', 'pattern',
        'minLength', 'maxLength', 'minimum', 'maximum', 'minItems', 'maxItems',
    ];

    public function test_gemini_schema_only_uses_documented_keywords(): void
    {
        $found = [];
        $this->collectKeywords((new DashboardSpecSchema)->forGemini(), $found);

        $this->assertSame([], array_values(array_intersect(self::GEMINI_UNSUPPORTED, $found)));
    }

    public function test_every_gemini_enum_declares_its_type(): void
    {
        $untyped = [];
        $this->collectUntypedEnums((new DashboardSpecSchema)->forGemini(), '$', $untyped);

        $this->assertSame([], $untyped);
    }

    public function test_gemini_schema_targets_the_latest_contract_and_lists_every_visualization_type(): void
    {
        $schema = (new DashboardSpecSchema)->forGemini();
        $visualization = $schema['properties']['visualizations']['items'];

        $this->assertSame(['1.1'], $schema['properties']['version']['enum']);
        $this->assertEqualsCanonicalizing(
            ['kpi', 'bar', 'line', 'area', 'pie', 'table'],
            $visualization['properties']['type']['enum'],
        );
        $this->assertSame([12], $schema['properties']['layout']['properties']['columns']['enum']);
        $this->assertSame('integer', $schema['properties']['layout']['properties']['columns']['type']);
        $this->assertSame(['key', 'title', 'type', 'query', 'display', 'layout'], $visualization['required']);
        $this->assertFalse($visualization['properties']['display']['additionalProperties']);
    }

    public function test_gemini_schema_keeps_metric_format_optional_and_free_of_null(): void
    {
        $metric = (new DashboardSpecSchema)->forGemini()['properties']['visualizations']['items']['properties']['query']['properties']['metrics']['items'];

        $this->assertNotContains('format', $metric['required']);
        $this->assertNotContains(null, $metric['properties']['format']['enum'], true);
    }

    public function test_gemini_schema_stays_small_enough_to_compile(): void
    {
        $size = strlen(json_encode((new DashboardSpecSchema)->forGemini(), JSON_THROW_ON_ERROR));

        // A guard against accidental growth, not a documented limit.
        $this->assertLessThan(8_000, $size);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    #[DataProvider('demoSpecs')]
    public function test_every_demo_spec_fits_the_gemini_schema_shape(array $definition): void
    {
        $schema = (new DashboardSpecSchema)->forGemini();

        $this->assertSame([], $this->shapeViolations($definition, $schema, '$'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function demoSpecs(): iterable
    {
        $generator = new FakeDashboardGenerator;

        foreach ([
            'regions' => 'ventas por region',
            'revenue' => 'comparar ingresos',
            'products' => 'productos con ventas disminuidas',
            'team' => 'rendimiento del equipo',
            'generic' => 'otra cosa',
        ] as $name => $prompt) {
            yield $name => [$generator->generate(new DashboardGenerationInput('t', 1, $prompt))->toArray()];
        }
    }

    public function test_the_openai_schema_is_not_affected_by_the_gemini_variant(): void
    {
        $schema = new DashboardSpecSchema;
        $schema->forGemini();
        $strict = $schema->forStructuredOutputs();

        $this->assertArrayHasKey('$defs', $strict);
        $this->assertSame('1.1', $strict['properties']['version']['const']);
    }

    /**
     * @param  array<int|string, mixed>  $node
     * @param  list<string>  $found
     */
    private function collectKeywords(array $node, array &$found, bool $isPropertyMap = false): void
    {
        foreach ($node as $key => $value) {
            // Property names are data, not keywords.
            if (! $isPropertyMap && is_string($key)) {
                $found[] = $key;
            }

            if (is_array($value)) {
                $this->collectKeywords($value, $found, $key === 'properties' && ! $isPropertyMap);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $untyped
     */
    private function collectUntypedEnums(array $node, string $path, array &$untyped): void
    {
        if (isset($node['enum']) && ! isset($node['type'])) {
            $untyped[] = $path;
        }

        foreach (['properties'] as $mapKey) {
            foreach ($node[$mapKey] ?? [] as $name => $child) {
                $this->collectUntypedEnums($child, "{$path}.{$name}", $untyped);
            }
        }

        if (isset($node['items']) && is_array($node['items'])) {
            $this->collectUntypedEnums($node['items'], "{$path}[]", $untyped);
        }

        foreach ($node['anyOf'] ?? [] as $index => $branch) {
            $this->collectUntypedEnums($branch, "{$path}|{$index}", $untyped);
        }
    }

    /**
     * Minimal structural check of a definition against object schemas: no
     * unknown keys and every required key present.
     *
     * @param  array<int|string, mixed>  $value
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private function shapeViolations(mixed $value, array $schema, string $path): array
    {
        $violations = [];

        if (isset($schema['properties']) && is_array($value)) {
            foreach (array_keys($value) as $key) {
                if (! array_key_exists($key, $schema['properties'])) {
                    $violations[] = "{$path}.{$key} is not allowed by the schema";
                }
            }

            foreach ($schema['required'] ?? [] as $required) {
                if (! array_key_exists($required, $value)) {
                    $violations[] = "{$path}.{$required} is required by the schema";
                }
            }

            foreach ($value as $key => $child) {
                if (isset($schema['properties'][$key])) {
                    array_push($violations, ...$this->shapeViolations($child, $schema['properties'][$key], "{$path}.{$key}"));
                }
            }
        }

        if (isset($schema['items']) && is_array($value) && array_is_list($value)) {
            foreach ($value as $index => $item) {
                array_push($violations, ...$this->shapeViolations($item, $schema['items'], "{$path}[{$index}]"));
            }
        }

        return $violations;
    }
}
