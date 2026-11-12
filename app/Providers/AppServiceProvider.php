<?php

namespace App\Providers;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Ai\Prompts\DashboardGenerationInstructions;
use App\Domain\Ai\Providers\FakeDashboardGenerator;
use App\Domain\Ai\Providers\OpenAiDashboardGenerator;
use App\Domain\Ai\Providers\UnsupportedDashboardGenerator;
use App\Domain\Ai\Schemas\DashboardSpecSchema;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Policies\DashboardPolicy;
use App\Domain\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class, fn () => new CurrentTenant);
        $this->app->bind(DashboardGenerator::class, function (): DashboardGenerator {
            $driver = config('dashboard-ai.driver');
            $normalizedDriver = is_string($driver) ? strtolower(trim($driver)) : '';

            return match ($normalizedDriver) {
                'fake' => new FakeDashboardGenerator,
                'openai' => new OpenAiDashboardGenerator(
                    apiKey: (string) config('dashboard-ai.openai.api_key', ''),
                    configuredModel: (string) config('dashboard-ai.openai.model', ''),
                    timeout: (int) config('dashboard-ai.openai.timeout', 60),
                    maxRetries: (int) config('dashboard-ai.openai.max_retries', 2),
                    schema: $this->app->make(DashboardSpecSchema::class),
                    instructions: $this->app->make(DashboardGenerationInstructions::class),
                ),
                default => new UnsupportedDashboardGenerator,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Dashboard::class, DashboardPolicy::class);
    }
}
