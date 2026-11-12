<?php

namespace App\Domain\Dashboards\Enums;

enum DashboardGenerationKind: string
{
    /** Builds a dashboard from a prompt. */
    case Create = 'create';

    /** Proposes a change to an existing dashboard; nothing is applied until the user accepts it. */
    case Refine = 'refine';
}
