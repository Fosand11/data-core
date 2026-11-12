<?php

namespace App\Domain\Ai\Data;

final readonly class DashboardGenerationInput
{
    /**
     * @param  array<string, mixed>|null  $currentSpec  When present, `prompt` is an instruction to change this existing spec.
     */
    public function __construct(
        public string $tenantId,
        public int $requestedBy,
        public string $prompt,
        public ?array $currentSpec = null,
    ) {}

    public function isRefinement(): bool
    {
        return $this->currentSpec !== null;
    }
}
