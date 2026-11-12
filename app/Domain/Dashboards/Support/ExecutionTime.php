<?php

namespace App\Domain\Dashboards\Support;

/**
 * An AI call can legitimately take longer than the time limit PHP applies to a
 * web request (30 s by default). When a job runs inside that request (the sync
 * queue used locally) PHP would kill it mid-call with a fatal error nobody can
 * catch, leaving the generation "processing" forever.
 *
 * A limit of 0 means unlimited (CLI and queue workers, whose timeouts are
 * enforced by the worker), so it is left alone.
 */
final class ExecutionTime
{
    public static function allow(int $seconds): void
    {
        $limit = (int) ini_get('max_execution_time');

        if ($limit > 0 && $limit < $seconds) {
            set_time_limit($seconds);
        }
    }
}
