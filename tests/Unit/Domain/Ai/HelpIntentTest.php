<?php

namespace Tests\Unit\Domain\Ai;

use App\Domain\Ai\Assistant\HelpIntent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HelpIntentTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: HelpIntent}>
     */
    public static function questionsAboutTheApp(): array
    {
        return [
            'visuals, plain' => ['¿Qué visuales tienes?', HelpIntent::Visuals],
            'visuals, charts' => ['qué gráficas hay disponibles', HelpIntent::Visuals],
            'visuals, types' => ['Cuáles son los tipos de gráfico', HelpIntent::Visuals],
            'visuals, available' => ['visuales disponibles', HelpIntent::Visuals],
            'visuals, what to chart' => ['¿Qué puedo graficar?', HelpIntent::Visuals],
            'suggestions, suggest' => ['Sugiéreme algo', HelpIntent::Suggestions],
            'suggestions, suggestions' => ['dame sugerencias', HelpIntent::Suggestions],
            'suggestions, ideas' => ['dame ideas', HelpIntent::Suggestions],
            'suggestions, what to ask' => ['¿Qué puedo preguntar?', HelpIntent::Suggestions],
            'suggestions, where to start' => ['no sé qué preguntar', HelpIntent::Suggestions],
            'suggestions, recommend' => ['¿Qué me recomiendas?', HelpIntent::Suggestions],
            'limits, what you cannot' => ['¿Qué no puedes hacer?', HelpIntent::Limits],
            'limits, limitations' => ['cuáles son tus limitaciones', HelpIntent::Limits],
            'overview, what can I do' => ['¿Qué puedo hacer?', HelpIntent::Overview],
            'overview, what can you do' => ['qué puedes hacer', HelpIntent::Overview],
            'overview, help' => ['ayuda', HelpIntent::Overview],
            'overview, how it works' => ['¿Cómo funciona?', HelpIntent::Overview],
            'accents and capitals do not matter' => ['¿QUÉ VISUALES TIENES?', HelpIntent::Visuals],
        ];
    }

    #[DataProvider('questionsAboutTheApp')]
    public function test_questions_about_the_app_are_recognised(string $prompt, HelpIntent $expected): void
    {
        $this->assertSame($expected, HelpIntent::fromPrompt($prompt));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function realAnalyticsRequests(): array
    {
        return [
            'sales by region' => ['Ventas por región'],
            'a trend' => ['Evolución mensual de los ingresos del último trimestre'],
            'names a chart type as an instruction' => ['Quiero un embudo, un treemap y un radar de ventas por equipo'],
            'the word ideas inside a request' => ['Reparto de ideas aprobadas por equipo en un gráfico circular'],
            'a change request' => ['Cambia las barras por un gráfico circular'],
            'asks for a specific chart with a metric' => ['Muestra las ventas en barras horizontales por producto'],
            'empty' => ['   '],
            'a long help-looking sentence is an analytics request' => [str_repeat('qué puedo hacer con las ventas por región y por producto ', 3)],
        ];
    }

    #[DataProvider('realAnalyticsRequests')]
    public function test_real_analytics_requests_are_left_for_the_generator(string $prompt): void
    {
        $this->assertNull(HelpIntent::fromPrompt($prompt));
    }

    public function test_the_most_specific_meaning_wins(): void
    {
        // "que no puedes hacer" also contains "puedes hacer".
        $this->assertSame(HelpIntent::Limits, HelpIntent::fromPrompt('qué no puedes hacer'));
        // "que graficas hay" is about visuals even though it mentions what exists.
        $this->assertSame(HelpIntent::Visuals, HelpIntent::fromPrompt('qué gráficas hay'));
    }
}
