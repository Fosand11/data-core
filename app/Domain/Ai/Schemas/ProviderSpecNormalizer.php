<?php

namespace App\Domain\Ai\Schemas;

/**
 * Removes the artefacts that provider-specific output schemas introduce, so the
 * definition reaches DashboardSpecValidator in the canonical shape. It only
 * deletes; it never invents or repairs values, so the validator stays the
 * authority on what is acceptable.
 */
final class ProviderSpecNormalizer
{
    /**
     * Which display keys belong to each visualization type.
     *
     * @var array<string, list<string>>
     */
    private const DISPLAY_KEYS = [
        'kpi' => ['show_comparison'],
        'bar' => ['x_axis', 'y_axis'],
        'line' => ['x_axis', 'y_axis'],
        'area' => ['x_axis', 'y_axis'],
        'pie' => ['category', 'value'],
        'table' => ['columns'],
    ];

    /**
     * `metric.format` is optional in the contract; some providers can only
     * express "absent" as null.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public static function dropNullMetricFormats(array $definition): array
    {
        foreach (self::visualizations($definition) as $index => $visualization) {
            $metrics = $visualization['query']['metrics'] ?? null;

            if (! is_array($metrics)) {
                continue;
            }

            foreach ($metrics as $metricIndex => $metric) {
                if (is_array($metric) && array_key_exists('format', $metric) && $metric['format'] === null) {
                    unset($definition['visualizations'][$index]['query']['metrics'][$metricIndex]['format']);
                }
            }
        }

        return $definition;
    }

    /**
     * Providers that use one flat `display` object may return keys that belong
     * to other visualization types; keep only the ones the type defines.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public static function pruneDisplayToType(array $definition): array
    {
        foreach (self::visualizations($definition) as $index => $visualization) {
            $type = $visualization['type'] ?? null;
            $display = $visualization['display'] ?? null;

            if (! is_string($type) || ! isset(self::DISPLAY_KEYS[$type]) || ! is_array($display)) {
                continue;
            }

            $definition['visualizations'][$index]['display'] = array_intersect_key(
                $display,
                array_flip(self::DISPLAY_KEYS[$type]),
            );
        }

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int|string, mixed>
     */
    private static function visualizations(array $definition): array
    {
        $visualizations = $definition['visualizations'] ?? null;

        return is_array($visualizations)
            ? array_filter($visualizations, 'is_array')
            : [];
    }
}
