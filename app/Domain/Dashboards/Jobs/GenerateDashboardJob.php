<?php

namespace App\Domain\Dashboards\Jobs;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Ai\Contracts\ModelAwareDashboardGenerator;
use App\Domain\Ai\Data\DashboardGenerationInput;
use App\Domain\Ai\Exceptions\AiGenerationException;
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

    public int $tries = 1;

    public int $timeout = 200;

    public function __construct(public readonly string $generationId) {}

    public function handle(DashboardGenerator $generator, DashboardSpecValidator $validator): void
    {
        try {
            $generation = $this->beginProcessing(
                $generator instanceof ModelAwareDashboardGenerator ? $generator->model() : null,
            );

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
                'error_code' => $this->errorCodeFor($exception),
            ]);
        }
    }

    private function beginProcessing(?string $model): ?DashboardGeneration
    {
        return DB::transaction(function () use ($model): ?DashboardGeneration {
            $generation = DashboardGeneration::query()->lockForUpdate()->findOrFail($this->generationId);
            $dashboard = Dashboard::query()->lockForUpdate()->findOrFail($generation->dashboard_id);

            $this->assertConsistentRelations($generation, $dashboard);

            if ($generation->status !== DashboardGenerationStatus::Queued || $dashboard->status->isTerminal()) {
                return null;
            }

            $now = now();
            $generation->update([
                'status' => DashboardGenerationStatus::Processing,
                'model' => $model,
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
        $errorCode = $this->errorCodeFor($exception);

        $message = match ($errorCode) {
            'DASHBOARD_SPEC_INVALID' => 'La definición generada no superó la validación de seguridad.',
            'UNSUPPORTED_PROVIDER' => 'El generador de dashboards no está configurado correctamente.',
            'INVALID_GENERATION_CONTEXT' => 'No fue posible validar el contexto de la generación.',
            'OPENAI_AUTHENTICATION_FAILED' => 'La integración con OpenAI no está configurada correctamente.',
            'OPENAI_RATE_LIMITED' => 'El servicio de generación alcanzó temporalmente su límite. Inténtalo más tarde.',
            'OPENAI_TIMEOUT' => 'El servicio de generación tardó demasiado en responder. Inténtalo nuevamente.',
            'OPENAI_INVALID_RESPONSE' => 'El servicio devolvió una definición que no pudo procesarse.',
            'OPENAI_UNAVAILABLE' => 'El servicio de generación no está disponible temporalmente.',
            'AI_GENERATION_REFUSED' => 'El modelo no pudo generar una definición para esta solicitud.',
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

    private function errorCodeFor(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof AiGenerationException => $exception->errorCode,
            $exception instanceof InvalidDashboardSpecException => 'DASHBOARD_SPEC_INVALID',
            $exception instanceof UnsupportedDashboardAiDriver => 'UNSUPPORTED_PROVIDER',
            $exception instanceof LogicException => 'INVALID_GENERATION_CONTEXT',
            default => 'GENERATION_FAILED',
        };
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
