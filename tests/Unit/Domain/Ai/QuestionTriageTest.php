<?php

namespace Tests\Unit\Domain\Ai;

use App\Domain\Ai\Assistant\QuestionTriage;
use App\Domain\Ai\Catalogs\DatasetCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuestionTriageTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function worthAskingTheModel(): array
    {
        return [
            'what is the product' => ['¿Qué es Atisbo?'],
            'how are numbers calculated' => ['¿Cómo se calculan los números?'],
            'is it safe' => ['¿Mis datos están seguros?'],
            'can it do something' => ['¿Puedes explicarme cómo funciona la paleta de colores?'],
            'who made it' => ['¿Quién te creó?'],
            'unrelated chat' => ['¿Cuál es la capital de Francia?'],
            'opens like a question without the marks' => ['como cambio el idioma'],
        ];
    }

    #[DataProvider('worthAskingTheModel')]
    public function test_questions_that_are_not_about_the_data_go_to_the_model(string $prompt): void
    {
        $this->assertTrue((new QuestionTriage)->shouldAsk($prompt, null), $prompt);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function leftForTheGenerator(): array
    {
        return [
            'a data question with a number word' => ['¿Cuánto vendimos en octubre?'],
            'asks which is best' => ['¿Cuál es el mejor producto?'],
            'names how to slice' => ['¿Qué pasa con las compras por país?'],
            'a ranking' => ['¿Quién es el top de ventas?'],
            'a plain request, not a question' => ['Muestra las ventas del año'],
            'too short to mean anything' => ['¿hola?'],
            'too long to be a quick question' => ['¿'.str_repeat('Cómo funciona la aplicación cuando tengo muchos datos y quiero ', 5).'?'],
        ];
    }

    #[DataProvider('leftForTheGenerator')]
    public function test_data_questions_and_requests_never_cost_a_model_call(string $prompt): void
    {
        $this->assertFalse((new QuestionTriage)->shouldAsk($prompt, null), $prompt);
    }

    public function test_a_question_that_names_one_of_the_users_columns_is_about_the_data(): void
    {
        $catalog = $this->catalog(['satisfaccion' => 'Satisfacción del cliente', 'canal' => 'Canal de venta']);

        $this->assertFalse((new QuestionTriage)->shouldAsk('¿Cómo está la satisfacción?', $catalog));
        $this->assertFalse((new QuestionTriage)->shouldAsk('¿Qué pasa con el canal?', $catalog));
        // The same sentence about something else is a question about the product.
        $this->assertTrue((new QuestionTriage)->shouldAsk('¿Cómo funciona el exportar?', $catalog));
    }

    /**
     * @param  array<string, string>  $labels  field => header
     */
    private function catalog(array $labels): DatasetCatalog
    {
        return new class($labels) implements DatasetCatalog
        {
            /** @param array<string, string> $labels */
            public function __construct(private readonly array $labels) {}

            public function dataset(): string
            {
                return 'ds_fixture';
            }

            public function fields(): array
            {
                return array_fill_keys(array_keys($this->labels), 'string');
            }

            public function labels(): array
            {
                return $this->labels;
            }

            public function hasField(string $field): bool
            {
                return isset($this->labels[$field]);
            }

            public function typeOf(string $field): ?string
            {
                return 'string';
            }

            public function dateField(): ?string
            {
                return null;
            }

            public function column(string $field): string
            {
                return $field;
            }
        };
    }
}
