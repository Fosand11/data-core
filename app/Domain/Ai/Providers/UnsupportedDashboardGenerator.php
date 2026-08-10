<?php

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Data\DashboardSpec;
use App\Domain\Ai\Exceptions\UnsupportedDashboardAiDriver;

final class UnsupportedDashboardGenerator implements DashboardGenerator
{
    public function provider(): string
    {
        return 'unsupported';
    }

    public function generate(DashboardGenerationInput $input): DashboardSpec
    {
        throw new UnsupportedDashboardAiDriver('The dashboard AI driver is not configured or supported.');
    }
}
