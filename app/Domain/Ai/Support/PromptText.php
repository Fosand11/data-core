<?php

namespace App\Domain\Ai\Support;

/**
 * Text from a customer's file, made safe to put in a prompt: one line, no control
 * characters, short, and quoted as JSON so it reads as a value and not as an instruction.
 */
final class PromptText
{
    public static function quote(string $text, int $max = 60): string
    {
        $clean = trim((string) preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', $text));
        $clean = mb_substr($clean, 0, $max);

        return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
