<?php

namespace App\Domain\Dashboards\Enums;

enum DatasetStatus: string
{
    /** Uploaded and analysed; waiting for the user to confirm the columns. */
    case Pending = 'pending';
    case Importing = 'importing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Ready, self::Failed], true);
    }
}
