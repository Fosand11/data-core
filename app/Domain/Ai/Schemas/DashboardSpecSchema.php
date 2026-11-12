<?php

namespace App\Domain\Ai\Schemas;

use JsonException;
use RuntimeException;

final class DashboardSpecSchema
{
    /**
     * Return the canonical schema adapted to the subset required by strict
     * Structured Outputs. Domain validation remains authoritative.
     *
     * @return array<string, mixed>
     */
    public function forStructuredOutputs(): array
    {
        $schema = $this->canonical();

        unset($schema['$schema'], $schema['$id']);
        // New generations always target the latest contract version.
        $schema['properties']['version'] = ['const' => '1.1'];
        $this->replaceConditionalVisualization($schema);

        /** @var array<string, mixed> $strictSchema */
        $strictSchema = $this->makeStrict($schema, '$');

        return $strictSchema;
    }

    /**
     * Schema for Gemini's `responseJsonSchema`. Gemini documents a subset of JSON
     * Schema and rejects schemas that are too large or branching, so this
     * variant has no `$ref`, `const`, `oneOf`, patterns or numeric/length
     * bounds, and uses one flat `display` object instead of a union per type.
     * Everything dropped here is enforced again by DashboardSpecValidator, and
     * ProviderSpecNormalizer prunes the flat display afterwards.
     *
     * @return array<string, mixed>
     */
    public function forGemini(): array
    {
        $schema = $this->canonical();
        unset($schema['$schema'], $schema['$id']);

        $schema['properties']['version'] = ['type' => 'string', 'enum' => ['1.1']];
        $this->replaceVisualizationWithFlatDisplay($schema);

        $definitions = $schema['$defs'];
        unset($schema['$defs']);

        /** @var array<string, mixed> $inlined */
        $inlined = $this->inlineReferences($schema, $definitions);

        /** @var array<string, mixed> $simplified */
        $simplified = $this->simplifyForGemini($inlined);

        return $simplified;
    }

    /**
     * @return array<string, mixed>
     */
    private function canonical(): array
    {
        $contents = file_get_contents(resource_path('schemas/dashboard-spec-v1.json'));

        if ($contents === false) {
            throw new RuntimeException('DashboardSpec schema could not be loaded.');
        }

        try {
            /** @var array<string, mixed> $schema */
            $schema = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('DashboardSpec schema is not valid JSON.', previous: $exception);
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function replaceVisualizationWithFlatDisplay(array &$schema): void
    {
        $properties = $schema['$defs']['visualization']['properties'] ?? null;

        if (! is_array($properties)) {
            throw new RuntimeException('DashboardSpec visualization schema is missing.');
        }

        $schema['$defs']['visualization'] = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['key', 'title', 'type', 'query', 'display', 'layout'],
            'properties' => [
                'key' => $properties['key'],
                'title' => $properties['title'],
                'type' => ['type' => 'string', 'enum' => ['kpi', 'bar', 'line', 'area', 'pie', 'table']],
                'query' => $properties['query'],
                'display' => [
                    'type' => 'object',
                    'description' => 'Fill only the keys of the visualization type: kpi uses show_comparison; bar, line and area use x_axis and y_axis; pie uses category and value; table uses columns.',
                    'additionalProperties' => false,
                    'properties' => [
                        'show_comparison' => ['type' => 'boolean'],
                        'x_axis' => ['$ref' => '#/$defs/fieldIdentifier'],
                        'y_axis' => ['$ref' => '#/$defs/fieldIdentifier'],
                        'category' => ['$ref' => '#/$defs/fieldIdentifier'],
                        'value' => ['$ref' => '#/$defs/fieldIdentifier'],
                        'columns' => ['type' => 'array', 'items' => ['$ref' => '#/$defs/fieldIdentifier']],
                    ],
                ],
                'layout' => $properties['layout'],
            ],
        ];
    }

    /**
     * Replaces every local `$ref` with the definition it points to. The schema
     * has no recursion, so this terminates.
     *
     * @param  array<string, array<string, mixed>>  $definitions
     */
    private function inlineReferences(mixed $node, array $definitions): mixed
    {
        if (! is_array($node)) {
            return $node;
        }

        if (isset($node['$ref']) && is_string($node['$ref'])) {
            $name = substr($node['$ref'], strlen('#/$defs/'));

            if (! isset($definitions[$name])) {
                throw new RuntimeException("DashboardSpec schema references an unknown definition [{$name}].");
            }

            return $this->inlineReferences($definitions[$name], $definitions);
        }

        foreach ($node as $key => $value) {
            $node[$key] = $this->inlineReferences($value, $definitions);
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function simplifyForGemini(array $schema): array
    {
        foreach (['pattern', 'minLength', 'maxLength', 'minimum', 'maximum', 'minItems', 'maxItems'] as $keyword) {
            unset($schema[$keyword]);
        }

        if (array_key_exists('oneOf', $schema)) {
            $schema['anyOf'] = $schema['oneOf'];
            unset($schema['oneOf']);
        }

        if (array_key_exists('const', $schema)) {
            $schema['type'] = is_int($schema['const']) ? 'integer' : 'string';
            $schema['enum'] = [$schema['const']];
            unset($schema['const']);
        }

        // Gemini documents enums under an explicit type.
        if (isset($schema['enum']) && ! isset($schema['type']) && is_array($schema['enum']) && $schema['enum'] !== []) {
            $schema['type'] = is_int($schema['enum'][0]) ? 'integer' : 'string';
        }

        foreach (['properties'] as $mapKey) {
            if (isset($schema[$mapKey]) && is_array($schema[$mapKey])) {
                foreach ($schema[$mapKey] as $name => $child) {
                    $schema[$mapKey][$name] = is_array($child) ? $this->simplifyForGemini($child) : $child;
                }
            }
        }

        foreach (['items', 'additionalProperties'] as $schemaKey) {
            if (isset($schema[$schemaKey]) && is_array($schema[$schemaKey])) {
                $schema[$schemaKey] = $this->simplifyForGemini($schema[$schemaKey]);
            }
        }

        if (isset($schema['anyOf']) && is_array($schema['anyOf'])) {
            $schema['anyOf'] = array_map(
                fn (mixed $branch): mixed => is_array($branch) ? $this->simplifyForGemini($branch) : $branch,
                $schema['anyOf'],
            );
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function replaceConditionalVisualization(array &$schema): void
    {
        $visualization = $schema['$defs']['visualization'] ?? null;

        if (! is_array($visualization) || ! isset($visualization['properties']) || ! is_array($visualization['properties'])) {
            throw new RuntimeException('DashboardSpec visualization schema is missing.');
        }

        $properties = $visualization['properties'];
        $base = [
            'key' => $properties['key'],
            'title' => $properties['title'],
            'query' => $properties['query'],
            'layout' => $properties['layout'],
        ];

        $schema['$defs']['visualization'] = [
            'anyOf' => [
                $this->visualizationVariant($base, 'kpi', [
                    'show_comparison' => ['type' => 'boolean'],
                ]),
                $this->visualizationVariant($base, 'bar', [
                    'x_axis' => ['$ref' => '#/$defs/fieldIdentifier'],
                    'y_axis' => ['$ref' => '#/$defs/fieldIdentifier'],
                ]),
                $this->visualizationVariant($base, 'line', [
                    'x_axis' => ['$ref' => '#/$defs/fieldIdentifier'],
                    'y_axis' => ['$ref' => '#/$defs/fieldIdentifier'],
                ]),
                $this->visualizationVariant($base, 'area', [
                    'x_axis' => ['$ref' => '#/$defs/fieldIdentifier'],
                    'y_axis' => ['$ref' => '#/$defs/fieldIdentifier'],
                ]),
                $this->visualizationVariant($base, 'pie', [
                    'category' => ['$ref' => '#/$defs/fieldIdentifier'],
                    'value' => ['$ref' => '#/$defs/fieldIdentifier'],
                ]),
                $this->visualizationVariant($base, 'table', [
                    'columns' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 12,
                        'items' => ['$ref' => '#/$defs/fieldIdentifier'],
                    ],
                ]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $displayProperties
     * @return array<string, mixed>
     */
    private function visualizationVariant(array $base, string $type, array $displayProperties): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                ...$base,
                'type' => ['const' => $type],
                'display' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => $displayProperties,
                ],
            ],
        ];
    }

    private function makeStrict(mixed $value, ?string $definitionName = null): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_key_exists('oneOf', $value)) {
            $value['anyOf'] = $value['oneOf'];
            unset($value['oneOf']);
        }

        if ($definitionName === 'metric' && isset($value['properties']['format']['enum'])) {
            $value['properties']['format']['enum'][] = null;
        }

        if (isset($value['properties']) && is_array($value['properties'])) {
            $value['required'] = array_keys($value['properties']);
            $value['additionalProperties'] = false;
        }

        foreach ($value as $key => $item) {
            $childDefinition = $definitionName;

            if ($key === '$defs' && is_array($item)) {
                foreach ($item as $name => $definition) {
                    $value[$key][$name] = $this->makeStrict($definition, (string) $name);
                }

                continue;
            }

            $value[$key] = $this->makeStrict($item, $childDefinition);
        }

        return $value;
    }
}
