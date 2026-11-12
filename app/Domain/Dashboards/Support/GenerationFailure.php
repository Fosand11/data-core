<?php

namespace App\Domain\Dashboards\Support;

use App\Domain\Ai\Exceptions\AiGenerationException;
use App\Domain\Ai\Exceptions\InvalidDashboardSpecException;
use App\Domain\Ai\Exceptions\UnsupportedDashboardAiDriver;
use App\Domain\Dashboards\Exceptions\RefinementProducedNoChanges;
use LogicException;
use Throwable;

/**
 * Turns any failure of a generation or refinement into a stable public code and
 * a safe, user-facing message. Provider details never reach the message.
 */
final readonly class GenerationFailure
{
    public function __construct(
        public string $code,
        public string $message,
    ) {}

    public static function from(Throwable $exception): self
    {
        $code = self::codeFor($exception);

        return new self($code, self::messageFor($code));
    }

    private static function codeFor(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof AiGenerationException => $exception->errorCode,
            $exception instanceof InvalidDashboardSpecException => 'DASHBOARD_SPEC_INVALID',
            $exception instanceof UnsupportedDashboardAiDriver => 'UNSUPPORTED_PROVIDER',
            $exception instanceof RefinementProducedNoChanges => 'NO_CHANGES',
            $exception instanceof LogicException => 'INVALID_GENERATION_CONTEXT',
            default => 'GENERATION_FAILED',
        };
    }

    private static function messageFor(string $code): string
    {
        return match ($code) {
            'DASHBOARD_SPEC_INVALID' => 'La definición generada no superó la validación de seguridad.',
            'UNSUPPORTED_PROVIDER' => 'El generador de dashboards no está configurado correctamente.',
            'INVALID_GENERATION_CONTEXT' => 'No fue posible validar el contexto de la generación.',
            'NO_CHANGES' => 'No encontré cambios que aplicar con esa instrucción. Prueba a ser más específico.',
            'OPENAI_AUTHENTICATION_FAILED' => 'La integración con OpenAI no está configurada correctamente.',
            'OPENAI_RATE_LIMITED', 'AI_RATE_LIMITED' => 'El servicio de generación alcanzó temporalmente su límite. Inténtalo más tarde.',
            'OPENAI_TIMEOUT', 'AI_TIMEOUT' => 'El servicio de generación tardó demasiado en responder. Inténtalo nuevamente.',
            'OPENAI_INVALID_RESPONSE', 'AI_INVALID_RESPONSE' => 'El servicio devolvió una definición que no pudo procesarse.',
            'OPENAI_UNAVAILABLE', 'AI_UNAVAILABLE' => 'El servicio de generación no está disponible temporalmente.',
            'AI_AUTHENTICATION_FAILED' => 'La integración con el proveedor de IA no está configurada correctamente.',
            'AI_GENERATION_REFUSED' => 'El modelo no pudo generar una definición para esta solicitud.',
            default => 'No fue posible generar el dashboard. Inténtalo nuevamente más tarde.',
        };
    }
}
