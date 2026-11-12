<?php

namespace App\Domain\Dashboards\Jobs;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Ai\Contracts\ModelAwareDashboardGenerator;
use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Exceptions\UnsupportedDashboardAiDriver;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use App\Domain\Dashboards\Enums\DashboardGenerationKind;
use App\Domain\Dashboards\Enums\DashboardGenerationStatus;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Exceptions\RefinementProducedNoChanges;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use App\Domain\Dashboards\Support\ExecutionTime;
use App\Domain\Dashboards\Support\GenerationFailure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * Asks the configured provider for a changed version of a ready dashboard and
 * stores it as a proposal. The dashboard itself is never touched here: it stays
 * ready and unchanged until the user applies the proposal.
 */
final class RefineDashboardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 200;

    public function __construct(public readonly string $generationId) {}

    public function handle(DashboardGenerator $generator, DashboardSpecValidator $validator): void
    {
        ExecutionTime::allow($this->timeout + 30);

        try {
            [$generation, $baseSpec] = $this->begin($generator instanceof ModelAwareDashboardGenerator ? $generator->model() : null) ?? [null, null];

            if ($generation === null) {
                return;
            }

            if ($generation->provider !== $generator->provider()) {
                throw new UnsupportedDashboardAiDriver('The configured provider does not match the queued refinement.');
            }

            $proposal = $validator->validate($generator->generate(new DashboardGenerationInput(
                tenantId: $generation->tenant_id,
                requestedBy: (int) $generation->requested_by,
                prompt: $generation->prompt,
                currentSpec: $baseSpec,
            )))->toArray();

            if ($this->normalize($proposal) === $this->normalize($baseSpec)) {
                throw new RefinementProducedNoChanges;
            }

            $this->complete($proposal);
        } catch (Throwable $exception) {
            $failure = GenerationFailure::from($exception);
            $this->fail($failure);

            Log::error('Dashboard refinement failed.', [
                'generation_id' => $this->generationId,
                'exception' => $exception::class,
                'error_code' => $failure->code,
            ]);
        }
    }

    /**
     * @return array{DashboardGeneration, array<string, mixed>}|null
     */
    private function begin(?string $model): ?array
    {
        return DB::transaction(function () use ($model): ?array {
            $generation = DashboardGeneration::query()->lockForUpdate()->findOrFail($this->generationId);
            $dashboard = Dashboard::query()->lockForUpdate()->findOrFail($generation->dashboard_id);

            if ($generation->tenant_id !== $dashboard->tenant_id) {
                throw new LogicException('Refinement tenant relationships are inconsistent.');
            }

            if ($generation->kind !== DashboardGenerationKind::Refine
                || $generation->status !== DashboardGenerationStatus::Queued
                || $generation->discarded_at !== null) {
                return null;
            }

            if ($dashboard->status !== DashboardStatus::Ready || ! is_array($dashboard->dashboard_spec)) {
                throw new LogicException('Only ready dashboards can be refined.');
            }

            $generation->update([
                'status' => DashboardGenerationStatus::Processing,
                'model' => $model,
                'started_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);

            return [$generation->fresh(), $dashboard->dashboard_spec];
        });
    }

    /**
     * @param  array<string, mixed>  $proposal
     */
    private function complete(array $proposal): void
    {
        DB::transaction(function () use ($proposal): void {
            $generation = DashboardGeneration::query()->lockForUpdate()->find($this->generationId);

            // A proposal the user discarded while it was running is dropped silently.
            if ($generation === null || $generation->status !== DashboardGenerationStatus::Processing || $generation->discarded_at !== null) {
                return;
            }

            $generation->update([
                'status' => DashboardGenerationStatus::Completed,
                'spec_version' => (string) $proposal['version'],
                'validated_spec' => $proposal,
                'completed_at' => now(),
            ]);
        });
    }

    private function fail(GenerationFailure $failure): void
    {
        DB::transaction(function () use ($failure): void {
            $generation = DashboardGeneration::query()->lockForUpdate()->find($this->generationId);

            if ($generation === null || $generation->status === DashboardGenerationStatus::Completed) {
                return;
            }

            $generation->update([
                'status' => DashboardGenerationStatus::Failed,
                'error_code' => $failure->code,
                'error_message' => $failure->message,
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * Canonical form so key order never makes two equal specs look different.
     *
     * @param  array<int|string, mixed>  $value
     */
    private function normalize(array $value): string
    {
        $sort = function (mixed $node) use (&$sort): mixed {
            if (! is_array($node)) {
                return $node;
            }

            $node = array_map($sort, $node);

            if (! array_is_list($node)) {
                ksort($node);
            }

            return $node;
        };

        return json_encode($sort($value), JSON_THROW_ON_ERROR);
    }
}
