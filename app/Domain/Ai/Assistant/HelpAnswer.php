<?php

namespace App\Domain\Ai\Assistant;

/**
 * The model's verdict on a message: a question about the product (with its answer), or a
 * request about the user's data that belongs to the dashboard generator.
 */
final readonly class HelpAnswer
{
    private function __construct(
        public bool $isHelp,
        public string $text,
    ) {}

    public static function help(string $text): self
    {
        return new self(true, $text);
    }

    public static function aboutData(): self
    {
        return new self(false, '');
    }
}
