<?php

namespace App\Domain\Metabase\Contracts;

interface DashboardPublisher
{
    /**
     * This boundary is intentionally not implemented during Phase 1.
     *
     * @param  array<string, mixed>  $dashboardSpec
     */
    public function publish(array $dashboardSpec): string;
}
