<?php

namespace App\Domain\Dashboards\Jobs;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Exceptions\InvalidDashboardSpecException;
use App\Domain\Ai\Exceptions\UnsupportedDashboardAiDriver;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use App\Domain\Dashboards\Enums\DashboardGenerationStatus;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

final class GenerateDashboardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public function __construct(public readonly string $generationId) {}

    public function handle(DashboardGenerator $generator, DashboardSpecValidator $validator): void
    {
        try {
            $generation = $this->beginProcessing();

            if ($generation === null) {
                return;
            }

            if ($generation->provider !== $generator->provider()) {
                throw new UnsupportedDashboardAiDriver('The configured provider does not match the queued generation.');
            }

            $spec = $generator->generate(new DashboardGenerationInput(
                tenantId: $generation->tenant_id,
                requestedBy: (int) $generation->requested_by,
                prompt: $generation->prompt,
            ));

            $validatedSpec = $validator->validate($spec)->toArray();
            $this->complete($validatedSpec);
        } catch (Throwable $exception) {
            $this->markFailed($exception);

            Log::error('Dashboard generation failed.', [
                'generation_id' => $this->generationId,
                'exception' => $exception::class,
                'technical_message' => $exception->getMessage(),
            ]);
        }
    }

    private function beginProcessing(): ?DashboardGeneration
    {
        return DB::transaction(function (): ?DashboardGeneration {
            $generation = DashboardGeneration::query()->lockForUpdate()->findOrFail($this->generationId);
            $dashboard = Dashboard::query()->lockForUpdate()->findOrFail($generation->dashboard_id);

            $this->assertConsistentRelations($generation, $dashboard);

            if ($generation->status !== DashboardGenerationStatus::Queued || $dashboard->status->isTerminal()) {
                return null;
            }

            $now = now();
            $generation->update([
                'status' => DashboardGenerationStatus::Processing,
                'started_at' => $now,
                'error_code' => null,
                'error_message' => null,
            ]);
            $dashboard->update([
                'status' => DashboardStatus::Generating,
                'generation_started_at' => $now,
                'error_message' => null,
            ]);

            return $generation->fresh(['dashboard']);
        });
    }

    /**
     * @param  array<string, mixed>  $validatedSpec
     */
    private function complete(array $validatedSpec): void
    {
        DB::transaction(function () use ($validatedSpec): void {
            $generation = DashboardGeneration::query()->lockForUpdate()->findOrFail($this->generationId);
            $dashboard = Dashboard::query()->lockForUpdate()->findOrFail($generation->dashboard_id);

            $this->assertConsistentRelations($generation, $dashboard);

            if ($generation->status === DashboardGenerationStatus::Completed || $dashboard->status === DashboardStatus::Ready) {
                return;
            }

            if ($generation->status !== DashboardGenerationStatus::Processing) {
                return;
            }

            $now = now();
            $version = (string) $validatedSpec['version'];

            $dashboard->update([
                'title' => $validatedSpec['title'],
                'description' => $validatedSpec['description'],
                'status' => DashboardStatus::Ready,
                'spec_version' => $version,
                'dashboard_spec' => $validatedSpec,
                'error_message' => null,
                'generation_completed_at' => $now,
            ]);
            $generation->update([
                'status' => DashboardGenerationStatus::Completed,
                'spec_version' => $version,
                'validated_spec' => $validatedSpec,
                'error_code' => null,
                'error_message' => null,
                'completed_at' => $now,
            ]);
        });
    }

    private function markFailed(Throwable $exception): void
    {
        $errorCode = match (true) {
            $exception instanceof InvalidDashboardSpecException => 'invalid_dashboard_spec',
            $exception instanceof UnsupportedDashboardAiDriver => 'unsupported_provider',
            $exception instanceof LogicException => 'invalid_generation_context',
            default => 'generation_failed',
        };

        $message = match ($errorCode) {
            'invalid_dashboard_spec' => 'La definición generada no superó la validación de seguridad.',
            'unsupported_provider' => 'El generador de dashboards no está configurado correctamente.',
            'invalid_generation_context' => 'No fue posible validar el contexto de la generación.',
            default => 'No fue posible generar el dashboard. Inténtalo nuevamente más tarde.',
        };

        DB::transaction(function () use ($errorCode, $message): void {
            $generation = DashboardGeneration::query()->lockForUpdate()->find($this->generationId);

            if ($generation === null || $generation->status === DashboardGenerationStatus::Completed) {
                return;
            }

            $dashboard = Dashboard::query()->lockForUpdate()->find($generation->dashboard_id);
            $now = now();

            $generation->update([
                'status' => DashboardGenerationStatus::Failed,
                'error_code' => $errorCode,
                'error_message' => $message,
                'completed_at' => $now,
            ]);

            if ($dashboard !== null && $dashboard->status !== DashboardStatus::Ready) {
                $dashboard->update([
                    'status' => DashboardStatus::Failed,
                    'error_message' => $message,
                    'generation_completed_at' => $now,
                ]);
            }
        });
    }

    private function assertConsistentRelations(DashboardGeneration $generation, Dashboard $dashboard): void
    {
        if ($generation->dashboard_id !== $dashboard->getKey() || $generation->tenant_id !== $dashboard->tenant_id) {
            throw new LogicException('Dashboard generation tenant relationships are inconsistent.');
        }

        if ($generation->requested_by !== null && $dashboard->created_by !== null && $generation->requested_by !== $dashboard->created_by) {
            throw new LogicException('Dashboard generation requester relationships are inconsistent.');
        }
    }
}
