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
        $contents = file_get_contents(base_path('docs/schemas/dashboard-spec-v1.json'));

        if ($contents === false) {
            throw new RuntimeException('DashboardSpec schema could not be loaded.');
        }

        try {
            /** @var array<string, mixed> $schema */
            $schema = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('DashboardSpec schema is not valid JSON.', previous: $exception);
        }

        unset($schema['$schema'], $schema['$id']);
        $this->replaceConditionalVisualization($schema);

        /** @var array<string, mixed> $strictSchema */
        $strictSchema = $this->makeStrict($schema, '$');

        return $strictSchema;
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
