<?php

namespace App\Domain\Ai\Assistant;

use App\Domain\Ai\Catalogs\DatasetCatalog;
use App\Domain\Ai\Catalogs\DemoSalesDatasetCatalog;
use App\Domain\Ai\Support\ColumnRoles;

/**
 * Questions worth asking about a dataset, built only from the names and types of its
 * columns (never from rows): a measure by a category, a trend when there is a date, a
 * ranking, a share, a comparison of two measures. Deterministic and free.
 */
final class QuestionSuggester
{
    /** @var list<string> */
    private const DEMO = [
        'Ventas por región',
        'Evolución mensual de los ingresos',
        'Ranking de productos por ventas en barras horizontales',
        'Reparto de las ventas por equipo en un gráfico circular',
        'Monto contra cantidad por región en una dispersión',
        'Un embudo de ventas por equipo',
    ];

    /**
     * @return list<string>
     */
    public function suggest(?DatasetCatalog $catalog, int $limit = 6): array
    {
        if ($catalog === null || $catalog->dataset() === DemoSalesDatasetCatalog::DATASET) {
            return array_slice(self::DEMO, 0, $limit);
        }

        $texts = ColumnRoles::groupable($catalog);
        $numbers = ColumnRoles::measurable($catalog);
        $date = $catalog->dateField();
        $label = fn (string $field): string => $this->label($catalog, $field);
        $text = $texts[0] ?? null;
        $other = $texts[1] ?? $text;
        $suggestions = [];

        if ($text !== null && $numbers !== []) {
            $suggestions[] = ucfirst($label($numbers[0]).' por '.$label($text));
        }

        if ($date !== null && $numbers !== []) {
            $suggestions[] = 'Evolución mensual de '.$label($numbers[0]);
        }

        if ($text !== null && $numbers !== []) {
            $suggestions[] = 'Ranking de '.$label($text).' por '.$label($numbers[0]).' en barras horizontales';
        }

        if ($other !== null && $numbers !== []) {
            $suggestions[] = 'Reparto de '.$label($numbers[0]).' por '.$label($other).' en un gráfico circular';
        }

        if ($text !== null && count($numbers) >= 2) {
            $suggestions[] = ucfirst($label($numbers[0]).' contra '.$label($numbers[1]).' por '.$label($text).' en una dispersión');
        }

        if ($other !== null && $numbers !== []) {
            $suggestions[] = 'Un embudo de '.$label($numbers[0]).' por '.$label($other);
        }

        if ($numbers !== []) {
            $suggestions[] = 'Promedio de '.$label($numbers[count($numbers) > 1 ? 1 : 0]).' en un medidor';
        }

        if ($text !== null && $numbers === []) {
            $suggestions[] = 'Cuántos registros hay por '.$label($text);
        }

        $suggestions[] = 'Un resumen general de mis datos';

        return array_slice(array_values(array_unique($suggestions)), 0, $limit);
    }

    /**
     * Changes worth asking of a dashboard that already exists.
     *
     * @return list<string>
     */
    public function changes(int $limit = 6): array
    {
        return array_slice([
            'Cambia las barras por un gráfico circular',
            'Agrega una tabla de detalle',
            'Cambia el gráfico por barras horizontales',
            'Muestra un embudo en vez de las barras',
            'Cambia las barras por un radar',
            'Quita los filtros',
        ], 0, $limit);
    }

    private function label(DatasetCatalog $catalog, string $field): string
    {
        $label = trim($catalog->labels()[$field] ?? '');

        return $label !== '' ? $label : str_replace('_', ' ', $field);
    }
}
