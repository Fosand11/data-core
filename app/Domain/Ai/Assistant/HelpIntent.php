<?php

namespace App\Domain\Ai\Assistant;

use Illuminate\Support\Str;

/**
 * What a user is asking when they do not want a dashboard but to know what the app can do.
 * Recognised from short, plain phrases; anything that looks like a real analytics request
 * ("ventas por región") is left for the dashboard generator.
 */
enum HelpIntent: string
{
    case Overview = 'overview';
    case Visuals = 'visuals';
    case Suggestions = 'suggestions';
    case Limits = 'limits';

    /** Longer sentences are analytics requests, not questions about the app. */
    private const MAX_LENGTH = 90;

    public static function fromPrompt(string $prompt): ?self
    {
        $text = self::normalize($prompt);

        if ($text === '' || mb_strlen($text) > self::MAX_LENGTH) {
            return null;
        }

        // Most specific first: "que no puedes hacer" also contains "puedes hacer".
        $patterns = [
            self::Limits->value => '/\b(que no (puedes|puedo|se puede|sabes|haces)|limitaciones|limites|restricciones|no puedes hacer|lo que no)\b/',
            self::Visuals->value => '/\b(que|cuales|cuantos|cuantas) (visuales|visualizaciones|graficos|graficas|tipos de (grafico|grafica|visual|visualizacion|graficos|graficas|visuales)|charts)\b|\btipos de (grafico|grafica|visual|visualizacion|graficos|graficas|visuales)\b|\b(visuales|graficos|graficas|visualizaciones) (disponibles|que tienes|que hay|que ofreces|que manejas|que puedo usar)\b|\bque puedo graficar\b/',
            self::Suggestions->value => '/\b(sugier[a-z]*|sugerenc[a-z]*|recomien[a-z]*|recomendac[a-z]*|inspir[a-z]*)\b|\b(dame|dime|tienes|dame unas?) ideas\b|\bque (puedo|podria|deberia|debo) (preguntar|pedir|ver|analizar|probar|consultar)\b|\bque preguntar\b|\bpor donde (empiezo|empezar|comienzo|comenzar)\b|\bno se que (preguntar|pedir|hacer)\b/',
            self::Overview->value => '/^(hola|ayuda|help|empezar|comenzar|hola ayuda)$|\b(que (puedes|puedo|sabes|podemos) hacer|que haces|para que sirves|como funciona|como te uso|como se usa|que eres|quien eres|como empiezo)\b/',
        ];

        foreach ($patterns as $intent => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return self::from($intent);
            }
        }

        return null;
    }

    /** Lower case, no accents or punctuation, single spaces. */
    private static function normalize(string $prompt): string
    {
        $ascii = Str::lower(Str::ascii($prompt));

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9 ]+/', ' ', $ascii)));
    }
}
