<?php

namespace App\Domain\Ai\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Retry policy and failure classification shared by HTTP based providers.
 */
final class TransientHttpFailures
{
    /**
     * Connection problems and 5xx are worth retrying. 429 is optional: a
     * per-minute quota will not recover within the short backoff, and a daily
     * quota never will, so providers with small quotas opt out.
     */
    public static function shouldRetry(Throwable $exception, bool $retryRateLimits = true): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        return $exception->response->serverError()
            || ($retryRateLimits && $exception->response->status() === 429);
    }

    /**
     * Bounded exponential backoff in milliseconds: 200, 400, 800, 1000...
     */
    public static function backoffMilliseconds(int $attempt): int
    {
        return min(1000, 200 * (2 ** max(0, $attempt - 1)));
    }

    public static function isTimeout(ConnectionException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'curl error 28');
    }
}
