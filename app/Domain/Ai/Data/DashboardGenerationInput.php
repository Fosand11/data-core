<?php

namespace App\Domain\Ai\Data;

final readonly class DashboardGenerationInput
{
    public function __construct(
        public string $tenantId,
        public int $requestedBy,
        public string $prompt,
    ) {}
}
