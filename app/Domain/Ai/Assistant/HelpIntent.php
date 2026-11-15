<?php

namespace App\Domain\Ai\Assistant;

use Illuminate\Support\Str;

/**
 * What a user is asking when they do not want a dashboard but to know what the app can do.
 *
 * It looks for two signals together instead of fixed phrases, because people word the same
 * question many ways ("¿qué tipo de gráficos puedo hacer?", "qué gráficas hay", "¿puedes hacer
 * un pastel?"): a TOPIC (visuals, ideas, limits, the app itself) and the SHAPE of a question
 * about it (a question word first, or "puedo / puedes / hay / tienes"…). A real analytics
 * request has a topic but no such shape ("Muestra las ventas en barras por región"), so it is
 * left for the dashboard generator.
 */
enum HelpIntent: string
{
    case Overview = 'overview';
    case Visuals = 'visuals';
    case Suggestions = 'suggestions';
    case Limits = 'limits';

    /** Longer sentences are analytics requests, not questions about the app. */
    private const MAX_LENGTH = 90;

    /** The sentence opens like a question. */
    private const QUESTION_OPENING = '/^(que|cual|cuales|cuantos|cuantas|como|puedo|puedes|puede|podria|podrias|podemos|tienes|tienen|tiene|hay|existen|se puede|se pueden|dime|explicame|ayudame|me puedes|me das|dame)\b/';

    /** A verb that asks about ability or availability, wherever it appears. */
    private const CAPABILITY = '/\b(puedo|puedes|podria|podrias|podemos|se puede|se pueden|tienes|tienen|hay|existen|ofreces|manejas|soportas|admites|disponibles?|permite|permites|incluye|incluyes|sabes hacer)\b/';

    public static function fromPrompt(string $prompt): ?self
    {
        $text = self::normalize($prompt);

        if ($text === '' || mb_strlen($text) > self::MAX_LENGTH) {
            return null;
        }

        $opensWithQuestion = preg_match(self::QUESTION_OPENING, $text) === 1;
        $asksAboutAbility = preg_match(self::CAPABILITY, $text) === 1;
        $shapedLikeAQuestion = $opensWithQuestion || $asksAboutAbility;
        // "por" means the person names how to slice their data: that is a request, not a question.
        $slicesData = preg_match('/\bpor\b/', $text) === 1;

        // What the app does not do. Checked first: "que no puedes hacer" also contains "puedes hacer".
        if (preg_match('/\b(que no (puedes|puedo|se puede|sabes|haces)|limitaciones|limites|restricciones|no puedes hacer|lo que no)\b/', $text) === 1
            || ($shapedLikeAQuestion && ! $slicesData && preg_match('/\b(mapa|mapas|geograf[a-z]*|coropletic[a-z]*)\b/', $text) === 1)) {
            return self::Limits;
        }

        // Which visuals exist.
        $visualsTopic = preg_match('/\b(grafic[a-z]*|visual(es)?|visualizacion(es)?|charts?|diagrama[s]?|pastel|tipos? de (grafic[a-z]*|visual[a-z]*)|clases? de (grafic[a-z]*|visual[a-z]*))\b/', $text) === 1;

        if ($visualsTopic && $shapedLikeAQuestion && ! $slicesData) {
            return self::Visuals;
        }

        // Ideas for what to ask.
        if (preg_match('/\b(sugier[a-z]*|sugerenc[a-z]*|recomien[a-z]*|recomendac[a-z]*|inspir[a-z]*)\b/', $text) === 1
            || preg_match('/\b(dame|dime|tienes|dame unas?) (ideas|ejemplos)\b|\bejemplos? de (preguntas?|cosas)\b|\bque (puedo|podria|deberia|debo) (preguntar|pedir|ver|analizar|probar|consultar|preguntarte)\b|\bque (preguntas|cosas) (puedo|podria|debo)\b|\bque preguntar\b|\bpor donde (empiezo|empezar|comienzo|comenzar)\b|\bno se que (preguntar|pedir|hacer)\b/', $text) === 1) {
            return self::Suggestions;
        }

        // The app itself.
        if (preg_match('/^(hola|ayuda|help|empezar|comenzar|hola ayuda)$/', $text) === 1
            || preg_match('/\b(que (tipo de cosas |cosas )?(puedes|puedo|sabes|podemos) hacer|que haces|para que sirves|como funciona|como te uso|como se usa|que eres|quien eres|como empiezo)\b/', $text) === 1) {
            return self::Overview;
        }

        return null;
    }

    /** Whether the sentence is shaped like a question: it opens like one, or asks about ability. */
    public static function isQuestionShaped(string $prompt): bool
    {
        $text = self::normalize($prompt);

        return preg_match(self::QUESTION_OPENING, $text) === 1
            || preg_match(self::CAPABILITY, $text) === 1
            || str_contains($prompt, '?')
            || str_contains($prompt, '¿');
    }

    /** Lower case, no accents or punctuation, single spaces. */
    public static function normalize(string $prompt): string
    {
        $ascii = Str::lower(Str::ascii($prompt));

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9 ]+/', ' ', $ascii)));
    }
}
