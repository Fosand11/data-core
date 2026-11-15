<?php

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\Assistant\HelpAnswer;
use App\Domain\Ai\Assistant\HelpQuestion;
use App\Domain\Ai\Contracts\HelpResponder;
use App\Domain\Ai\Exceptions\AiGenerationException;

/**
 * Used when the configured driver has no help implementation: the caller falls back to the
 * fixed answers, which is always safe.
 */
final class UnsupportedHelpResponder implements HelpResponder
{
    public function provider(): string
    {
        return 'unsupported';
    }

    public function answer(HelpQuestion $question): HelpAnswer
    {
        throw new AiGenerationException('AI_UNAVAILABLE', 'La ayuda con IA no está disponible con este proveedor.');
    }
}
