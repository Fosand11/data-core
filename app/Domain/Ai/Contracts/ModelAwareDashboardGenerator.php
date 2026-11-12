<?php

namespace App\Domain\Ai\Contracts;

interface ModelAwareDashboardGenerator
{
    public function model(): string;
}
