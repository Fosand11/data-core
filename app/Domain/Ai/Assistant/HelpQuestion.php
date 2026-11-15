<?php

namespace App\Domain\Ai\Assistant;

/**
 * What a help responder receives: the user's question (cleaned and shortened) and the
 * fact sheet to answer from. Nothing else about the user or their data.
 */
final readonly class HelpQuestion
{
    public function __construct(
        public string $text,
        public string $facts,
    ) {}

    /**
     * A question for a model: control characters removed, spaces collapsed, cut to the limit.
     */
    public static function from(string $text, string $facts, int $maxChars): self
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text)));

        return new self(mb_substr($clean, 0, max(1, $maxChars)), $facts);
    }
}
