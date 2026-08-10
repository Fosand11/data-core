<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardManagementController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Tenancy\SelectTenantController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('health', HealthController::class)->name('health');

Route::middleware(['auth', 'verified', 'current_tenant'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboards', [DashboardManagementController::class, 'index'])->name('dashboards.index');
    Route::get('dashboards/create', [DashboardManagementController::class, 'create'])->name('dashboards.create');
    Route::post('dashboards', [DashboardManagementController::class, 'store'])->name('dashboards.store');
    Route::get('dashboards/{dashboard}', [DashboardManagementController::class, 'show'])->name('dashboards.show');
    Route::patch('organizations/{tenant:slug}/select', SelectTenantController::class)
        ->name('tenants.select');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
