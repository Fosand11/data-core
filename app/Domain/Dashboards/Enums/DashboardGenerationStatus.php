<?php

namespace App\Domain\Dashboards\Enums;

enum DashboardGenerationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
