<?php

namespace App\Providers;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Ai\Providers\FakeDashboardGenerator;
use App\Domain\Ai\Providers\UnsupportedDashboardGenerator;
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

            return is_string($driver) && strtolower(trim($driver)) === 'fake'
                ? new FakeDashboardGenerator
                : new UnsupportedDashboardGenerator;
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
