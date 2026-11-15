<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\Assistant\HelpAnswer;
use App\Domain\Ai\Assistant\HelpQuestion;
use App\Domain\Ai\Exceptions\AiGenerationException;

/**
 * Answers a question about the product from a fixed fact sheet. Implementations must not
 * be given, and must not send, anything about the user beyond the question itself: no
 * identifiers, no rows.
 */
interface HelpResponder
{
    public function provider(): string;

    /**
     * @throws AiGenerationException when the provider cannot answer; the caller falls back to fixed content
     */
    public function answer(HelpQuestion $question): HelpAnswer;
}
