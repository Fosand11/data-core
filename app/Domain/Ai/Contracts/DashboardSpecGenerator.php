<?php

namespace App\Domain\Ai\Contracts;

interface DashboardSpecGenerator
{
    /**
     * This boundary is intentionally not implemented during Phase 1.
     *
     * @return array<string, mixed>
     */
    public function generate(string $prompt): array;
}
