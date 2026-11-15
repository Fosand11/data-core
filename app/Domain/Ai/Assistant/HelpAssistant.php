<?php

namespace App\Domain\Ai\Assistant;

use App\Domain\Ai\Catalogs\DatasetCatalog;
use App\Domain\Ai\Catalogs\DemoSalesDatasetCatalog;
use App\Domain\Ai\Support\ColumnRoles;

/**
 * Answers "what can I do here?" without a model: what the visuals are, which ones the
 * current data supports, what to ask, and what the app does not do. The text is fixed (no
 * data, no user input), so there is nothing to leak and nothing to spend.
 */
final class HelpAssistant
{
    public function __construct(private readonly QuestionSuggester $suggester) {}

    /**
     * @return array{
     *     kind: string,
     *     title: string,
     *     message: string,
     *     dataset: string|null,
     *     visuals: list<array{type: string, name: string, description: string, example: string, available: bool, note: string|null}>|null,
     *     suggestions: list<string>,
     *     tips: list<string>|null,
     *     limits: list<string>|null
     * }
     */
    public function reply(HelpIntent $intent, ?DatasetCatalog $catalog, ?string $datasetName = null, bool $refinement = false): array
    {
        $base = ['kind' => $intent->value, 'dataset' => $datasetName, 'visuals' => null, 'suggestions' => [], 'tips' => null, 'limits' => null];
        $changes = $refinement ? $this->suggester->changes() : null;

        return match ($intent) {
            HelpIntent::Visuals => [
                ...$base,
                'title' => 'Los visuales disponibles',
                'message' => $refinement
                    ? 'Estos son los tipos de visual. Puedes pedir que cambie cualquier gráfica de este dashboard por otro.'
                    : 'Estos son los tipos de visual que puedo dibujar. Elijo el que mejor responde a tu pregunta, o puedes pedir uno en concreto.',
                'visuals' => $this->visuals($catalog),
                'suggestions' => $changes ?? $this->suggester->suggest($catalog, 3),
            ],
            HelpIntent::Suggestions => [
                ...$base,
                'title' => $refinement ? 'Cambios que puedes pedir' : 'Ideas para empezar',
                'message' => $refinement
                    ? 'Toca uno para llenar el cuadro y ajústalo si quieres. Verás una vista previa y decides si aplicarlo.'
                    : ($datasetName === null
                        ? 'Con los datos de ejemplo (ventas de 12 meses) puedes probar estas preguntas. Toca una para llenar el cuadro.'
                        : "Con tus datos de «{$datasetName}» te propongo estas preguntas. Toca una para llenar el cuadro."),
                'suggestions' => $changes ?? $this->suggester->suggest($catalog),
            ],
            HelpIntent::Limits => [
                ...$base,
                'title' => 'Lo que no hago (todavía)',
                'message' => 'Para que sepas qué esperar de la herramienta:',
                'limits' => $this->limits(),
                'suggestions' => $changes !== null ? [] : $this->suggester->suggest($catalog, 3),
            ],
            HelpIntent::Overview => [
                ...$base,
                'title' => '¿Qué puedes hacer aquí?',
                'message' => $refinement
                    ? 'Describe un cambio y te muestro una propuesta para que la compares con el dashboard actual antes de aplicarla.'
                    : 'Escribe en lenguaje natural lo que quieres entender de tus datos y diseño el dashboard: indicadores, gráficas y tablas. Los números se calculan en el servidor, a partir de tus filas.',
                'suggestions' => $changes ?? $this->suggester->suggest($catalog, 4),
                'tips' => [
                    'Pregúntame «¿qué visuales tienes?» para ver los tipos de gráfica.',
                    'Pídeme ideas con «sugiéreme algo» y te propongo preguntas sobre tus datos.',
                    'Sube un CSV o un Excel en «Datos» para preguntar sobre tu propia información.',
                    'Cambia colores y paleta con el botón «Colores» y exporta a PDF, PowerPoint, PNG o CSV.',
                    'Pregunta «¿qué no puedes hacer?» para conocer los límites.',
                ],
            ],
        };
    }

    /**
     * @return list<array{type: string, name: string, description: string, example: string, available: bool, note: string|null}>
     */
    private function visuals(?DatasetCatalog $catalog): array
    {
        $isDemo = $catalog === null || $catalog->dataset() === DemoSalesDatasetCatalog::DATASET;
        $hasText = $isDemo || ColumnRoles::byUsefulness($catalog, 'string') !== [];
        $numbers = $isDemo ? 2 : count(ColumnRoles::byUsefulness($catalog, 'numeric'));
        $hasDate = $isDemo || $catalog->dateField() !== null;
        $examples = $this->examples($catalog);

        $noText = 'Tus datos no tienen una columna de texto para agrupar.';
        $rows = [
            ['kpi', 'Indicador', 'Un número clave: un total, un promedio o un conteo.', true, null],
            ['bar', 'Barras', 'Compara categorías entre sí.', $hasText, $noText],
            ['hbar', 'Barras horizontales', 'Rankings y categorías con nombres largos.', $hasText, $noText],
            ['line', 'Línea', 'Cómo cambia algo a lo largo del tiempo.', $hasDate, 'Tus datos no tienen una columna de fecha.'],
            ['area', 'Área', 'Como la línea, pero resalta el volumen acumulado.', $hasDate, 'Tus datos no tienen una columna de fecha.'],
            ['pie', 'Circular', 'Cómo se reparte un total entre pocas categorías (hasta 6).', $hasText, $noText],
            ['funnel', 'Embudo', 'Etapas que se van reduciendo, de la mayor a la menor.', $hasText, $noText],
            ['treemap', 'Mapa de árbol', 'Cómo se divide un total entre muchas categorías, por áreas.', $hasText, $noText],
            ['radar', 'Radar', 'Compara una medida entre 3 y 8 categorías.', $hasText, $noText],
            ['scatter', 'Dispersión', 'La relación entre dos medidas, un punto por categoría.', $hasText && $numbers >= 2, 'Necesita dos columnas numéricas y una de texto.'],
            ['gauge', 'Medidor', 'Un valor frente a un máximo, como una calificación de 1 a 10.', $numbers >= 1, 'Tus datos no tienen una columna numérica.'],
            ['table', 'Tabla', 'Resúmenes con el detalle de cada categoría.', $hasText, $noText],
        ];

        return array_map(fn (array $row): array => [
            'type' => $row[0],
            'name' => $row[1],
            'description' => $row[2],
            'example' => $examples[$row[0]],
            'available' => $row[3],
            'note' => $row[3] ? null : $row[4],
        ], $rows);
    }

    /**
     * One example phrase per visual, written with the dataset's own column names.
     *
     * @return array<string, string>
     */
    private function examples(?DatasetCatalog $catalog): array
    {
        if ($catalog === null || $catalog->dataset() === DemoSalesDatasetCatalog::DATASET) {
            return [
                'kpi' => 'Total de ventas y número de pedidos',
                'bar' => 'Ventas por región',
                'hbar' => 'Ranking de productos por ventas, en barras horizontales',
                'line' => 'Evolución mensual de los ingresos',
                'area' => 'Ingresos acumulados por mes, en un gráfico de área',
                'pie' => 'Reparto de las ventas por equipo, en un gráfico circular',
                'funnel' => 'Un embudo de ventas por equipo',
                'treemap' => 'Cómo se reparten las ventas entre productos, en un mapa de árbol',
                'radar' => 'Ventas por vendedor en un radar',
                'scatter' => 'Monto contra cantidad por región, en una dispersión',
                'gauge' => 'Cantidad promedio por pedido, en un medidor',
                'table' => 'Una tabla con el detalle por producto',
            ];
        }

        $texts = ColumnRoles::groupable($catalog);
        $numbers = ColumnRoles::measurable($catalog);
        $label = fn (?string $field): string => $field === null ? 'tus datos' : (trim($catalog->labels()[$field] ?? '') ?: str_replace('_', ' ', $field));
        $text = $label($texts[0] ?? null);
        $other = $label($texts[1] ?? $texts[0] ?? null);
        $number = $label($numbers[0] ?? null);
        $second = $label($numbers[1] ?? $numbers[0] ?? null);

        return [
            'kpi' => "El total de {$number} y cuántos registros hay",
            'bar' => "{$number} por {$text}",
            'hbar' => "Ranking de {$text} por {$number}, en barras horizontales",
            'line' => "Evolución mensual de {$number}",
            'area' => "{$number} acumulado por mes, en un gráfico de área",
            'pie' => "Reparto de {$number} por {$other}, en un gráfico circular",
            'funnel' => "Un embudo de {$number} por {$other}",
            'treemap' => "Cómo se reparte {$number} entre {$other}, en un mapa de árbol",
            'radar' => "{$number} por {$other} en un radar",
            'scatter' => "{$number} contra {$second} por {$text}, en una dispersión",
            'gauge' => "Promedio de {$second}, en un medidor",
            'table' => "Una tabla con el detalle de {$number} por {$text}",
        ];
    }

    /**
     * @return list<string>
     */
    private function limits(): array
    {
        $maxMb = round((int) config('datasets.max_file_kb') / 1024, 1);

        return [
            'No tengo mapas geográficos todavía: para ubicaciones, lo más cercano es un ranking por región o país.',
            'Cada gráfica muestra una sola medida a la vez. Las barras apiladas o agrupadas y las varias líneas en una gráfica llegarán después.',
            'No escribo ni modifico tus datos: solo los consulto para dibujar los dashboards.',
            'No ejecuto SQL ni código. La IA solo diseña el dashboard con tus columnas y los números los calcula el servidor.',
            'Nunca envío tus filas a la IA: solo ve los nombres de tus columnas.',
            'Un dashboard tiene hasta 12 visualizaciones, y una gráfica circular hasta 6 porciones.',
            "Tus archivos pueden ser CSV o Excel (.xlsx), de hasta {$maxMb} MB, ".number_format((int) config('datasets.max_rows'), 0, ',', '.').' filas y '.(int) config('datasets.max_columns').' columnas, con un máximo de '.(int) config('datasets.max_per_tenant').' conjuntos por organización.',
            'Los datos de ejemplo son ventas ficticias; no representan a ninguna empresa real.',
        ];
    }
}
