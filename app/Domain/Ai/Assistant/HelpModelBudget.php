<?php

namespace App\Domain\Ai\Assistant;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * How many questions the model may answer: a small allowance per user per hour and a shared
 * one per day, so a public demo cannot be turned into a free chat or drain its quota. A
 * question that is refused here is answered from fixed content instead; it is not an error.
 */
final class HelpModelBudget
{
    private const GLOBAL_KEY = 'help-model:global-day';

    public function allow(User $user): bool
    {
        $userKey = 'help-model:hour:'.$user->getAuthIdentifier();
        $perHour = max(1, (int) config('dashboard-ai.help.rate_limit.per_hour'));
        $global = (int) config('dashboard-ai.help.rate_limit.global_per_day');

        if (RateLimiter::tooManyAttempts($userKey, $perHour) || ($global > 0 && RateLimiter::tooManyAttempts(self::GLOBAL_KEY, $global))) {
            return false;
        }

        RateLimiter::hit($userKey, 3600);

        if ($global > 0) {
            RateLimiter::hit(self::GLOBAL_KEY, 86400);
        }

        return true;
    }
}
