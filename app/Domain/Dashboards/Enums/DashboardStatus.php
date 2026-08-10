<?php

namespace App\Domain\Dashboards\Enums;

enum DashboardStatus: string
{
    case Queued = 'queued';
    case Generating = 'generating';
    case Ready = 'ready';
    case Failed = 'failed';
    case Archived = 'archived';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Ready, self::Failed, self::Archived], true);
    }
}
