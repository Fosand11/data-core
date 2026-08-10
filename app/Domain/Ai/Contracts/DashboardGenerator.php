<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Data\DashboardSpec;

interface DashboardGenerator
{
    public function provider(): string;

    public function generate(DashboardGenerationInput $input): DashboardSpec;
}
