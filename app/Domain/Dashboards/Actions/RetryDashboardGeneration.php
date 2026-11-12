<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Dashboards\Enums\DashboardGenerationStatus;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Jobs\GenerateDashboardJob;
use App\Domain\Dashboards\Models\Dashboard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class RetryDashboardGeneration
{
    public function __construct(private DashboardGenerator $generator) {}

    /**
     * Re-runs a failed generation with the original prompt and the provider
     * that is configured now. Ready dashboards are never overwritten.
     */
    public function execute(User $user, Dashboard $dashboard): Dashboard
    {
        Gate::forUser($user)->authorize('retry', $dashboard);

        if (! $dashboard->canBeRetried()) {
            throw ValidationException::withMessages([
                'dashboard' => 'Solo se puede reintentar un dashboard cuya generación falló o quedó atascada.',
            ]);
        }

        $provider = $this->generator->provider();

        $generation = DB::transaction(function () use ($dashboard, $user, $provider) {
            // Close any interrupted attempt so a late job can never resurrect it.
            $dashboard->generations()
                ->whereIn('status', [DashboardGenerationStatus::Queued, DashboardGenerationStatus::Processing])
                ->update([
                    'status' => DashboardGenerationStatus::Failed,
                    'error_code' => 'GENERATION_ABANDONED',
                    'error_message' => 'La generación se interrumpió y se reintentó.',
                    'completed_at' => now(),
                ]);

            $dashboard->update([
                'status' => DashboardStatus::Queued,
                'provider' => $provider,
                'error_message' => null,
                'generation_started_at' => null,
                'generation_completed_at' => null,
            ]);

            return $dashboard->generations()->create([
                'tenant_id' => $dashboard->tenant_id,
                'requested_by' => $user->getKey(),
                'provider' => $provider,
                'status' => DashboardGenerationStatus::Queued,
                'prompt' => $dashboard->original_prompt,
            ]);
        });

        GenerateDashboardJob::dispatch($generation->getKey())->afterCommit();

        return $dashboard;
    }
}
