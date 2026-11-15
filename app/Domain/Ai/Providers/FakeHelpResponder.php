<?php

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\Assistant\HelpAnswer;
use App\Domain\Ai\Assistant\HelpQuestion;
use App\Domain\Ai\Contracts\HelpResponder;

/**
 * Deterministic stand-in for development and tests: no network, always the same answer.
 * A question containing the word "datos-simulados" is reported as a request about data, so
 * both outcomes can be exercised.
 */
final class FakeHelpResponder implements HelpResponder
{
    public function provider(): string
    {
        return 'fake';
    }

    public function answer(HelpQuestion $question): HelpAnswer
    {
        if (str_contains(mb_strtolower($question->text), 'datos-simulados')) {
            return HelpAnswer::aboutData();
        }

        return HelpAnswer::help('Puedo convertir lo que escribas en un dashboard con indicadores, gráficas y tablas sobre tus datos. Pregúntame qué visuales tienes o pídeme ideas para empezar.');
    }
}
