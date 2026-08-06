<?php

namespace App\Providers;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Dashboard::class, DashboardPolicy::class);
    }
}
