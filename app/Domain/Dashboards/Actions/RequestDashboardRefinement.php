<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Dashboards\Enums\DashboardGenerationKind;
use App\Domain\Dashboards\Enums\DashboardGenerationStatus;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Jobs\RefineDashboardJob;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use App\Domain\Dashboards\Support\DashboardVersions;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class RequestDashboardRefinement
{
    public function __construct(
        private DashboardGenerator $generator,
        private DashboardVersions $versions,
    ) {}

    /**
     * Queues a proposal for changing a ready dashboard. Nothing changes on the
     * dashboard until the user applies the proposal.
     */
    public function execute(User $user, Dashboard $dashboard, string $instruction): DashboardGeneration
    {
        Gate::forUser($user)->authorize('refine', $dashboard);

        $instruction = trim($instruction);

        if ($dashboard->status !== DashboardStatus::Ready || ! is_array($dashboard->dashboard_spec)) {
            throw ValidationException::withMessages([
                'instruction' => 'Solo se pueden ajustar dashboards que ya están listos.',
            ]);
        }

        $provider = $this->generator->provider();

        $generation = DB::transaction(function () use ($user, $dashboard, $instruction, $provider): DashboardGeneration {
            // Serialize concurrent requests for the same dashboard.
            $locked = Dashboard::query()->lockForUpdate()->findOrFail($dashboard->getKey());

            $this->assertNothingRunning($locked);
            $this->discardUnresolved($locked);
            $this->versions->ensureInitial($locked);

            return $locked->generations()->create([
                'tenant_id' => $locked->tenant_id,
                'requested_by' => $user->getKey(),
                'provider' => $provider,
                'kind' => DashboardGenerationKind::Refine,
                'status' => DashboardGenerationStatus::Queued,
                'base_version' => $this->versions->currentNumber($locked),
                'prompt' => $instruction,
            ]);
        });

        RefineDashboardJob::dispatch($generation->getKey())->afterCommit();

        return $generation;
    }

    private function assertNothingRunning(Dashboard $dashboard): void
    {
        $staleAfter = max(1, (int) config('dashboard-ai.stale_after_minutes', 10));

        $running = $dashboard->generations()->unresolvedRefinements()
            ->whereIn('status', [DashboardGenerationStatus::Queued, DashboardGenerationStatus::Processing])
            ->get();

        foreach ($running as $generation) {
            if ($generation->updated_at->gte(now()->subMinutes($staleAfter))) {
                throw ValidationException::withMessages([
                    'instruction' => 'Ya hay un ajuste en curso para este dashboard. Espera a que termine.',
                ]);
            }

            // An interrupted attempt must not block the dashboard forever.
            $generation->update([
                'status' => DashboardGenerationStatus::Failed,
                'error_code' => 'GENERATION_ABANDONED',
                'error_message' => 'El ajuste se interrumpió.',
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Only one proposal is shown at a time: a new request replaces any earlier
     * one that was waiting for a decision or had failed.
     */
    private function discardUnresolved(Dashboard $dashboard): void
    {
        $dashboard->generations()->unresolvedRefinements()->update(['discarded_at' => now()]);
    }
}
