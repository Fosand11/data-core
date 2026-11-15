<?php

namespace App\Domain\Ai\Assistant;

use App\Domain\Ai\Catalogs\DatasetCatalog;

/**
 * Decides whether a message the fixed rules did not recognise is worth sending to the
 * model. Most are not: a question about the user's own data ("¿cuánto vendimos en octubre?")
 * belongs to the dashboard generator and must not cost an extra call, so only messages that
 * are shaped like a question and do not talk about the data qualify.
 */
final class QuestionTriage
{
    /** Words that mean "this is about the numbers", not about the product. */
    private const ANALYTICS_WORDS = '/\b(cuanto|cuantos|cuantas|total|totales|promedio|suma|sumar|ventas?|vendimos|vendi|vendemos|ingresos?|gastos?|monto|compras?|clientes?|pedidos?|productos?|regiones?|region|mes|meses|ano|anos|trimestre|semana|semanas|dia|dias|mayor|menor|mejor|peor|top|ranking|evolucion|tendencia|compar[a-z]*|crecimiento|porcentaje|margen|utilidad|usuarios?|empleados?|vendedor(es)?|equipo|registros?|filas|columnas?)\b/';

    private const MIN_LENGTH = 6;

    public function shouldAsk(string $prompt, ?DatasetCatalog $catalog): bool
    {
        $text = HelpIntent::normalize($prompt);

        if (mb_strlen($text) < self::MIN_LENGTH || mb_strlen($prompt) > (int) config('dashboard-ai.help.max_question_chars')) {
            return false;
        }

        if (! HelpIntent::isQuestionShaped($prompt)) {
            return false;
        }

        // "por" names how to slice the data: a request, not a question about the product.
        if (preg_match('/\bpor\b/', $text) === 1 || preg_match(self::ANALYTICS_WORDS, $text) === 1) {
            return false;
        }

        return ! $this->mentionsAColumn($text, $catalog);
    }

    private function mentionsAColumn(string $text, ?DatasetCatalog $catalog): bool
    {
        if ($catalog === null) {
            return false;
        }

        $words = array_flip(explode(' ', $text));

        foreach ($catalog->fields() as $field => $type) {
            $name = HelpIntent::normalize(($catalog->labels()[$field] ?? '') !== '' ? $catalog->labels()[$field] : str_replace('_', ' ', $field));

            foreach (explode(' ', $name) as $word) {
                if (mb_strlen($word) >= 4 && isset($words[$word])) {
                    return true;
                }
            }
        }

        return false;
    }
}
