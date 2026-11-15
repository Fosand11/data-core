<?php

use App\Http\Controllers\DashboardActionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardManagementController;
use App\Http\Controllers\DashboardRefinementController;
use App\Http\Controllers\DashboardSearchController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\Tenancy\SelectTenantController;
use App\Http\Middleware\AnswerHelpQuestions;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('health', HealthController::class)->name('health');
Route::get('privacidad', PrivacyPolicyController::class)->name('privacy');

Route::middleware(['auth', 'verified', 'current_tenant'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboards', [DashboardManagementController::class, 'index'])->name('dashboards.index');
    Route::get('dashboards/search', DashboardSearchController::class)
        ->middleware('throttle:60,1')
        ->name('dashboards.search');
    Route::get('dashboards/create', [DashboardManagementController::class, 'create'])->name('dashboards.create');
    Route::post('dashboards', [DashboardManagementController::class, 'store'])
        ->middleware(AnswerHelpQuestions::class)
        ->name('dashboards.store');
    Route::get('dashboards/{dashboard}', [DashboardManagementController::class, 'show'])->name('dashboards.show');
    Route::patch('dashboards/{dashboard}', [DashboardManagementController::class, 'update'])->name('dashboards.update');
    Route::delete('dashboards/{dashboard}', [DashboardManagementController::class, 'destroy'])->name('dashboards.destroy');
    Route::post('dashboards/{dashboard}/duplicate', [DashboardActionController::class, 'duplicate'])->name('dashboards.duplicate');
    Route::patch('dashboards/{dashboard}/pin', [DashboardActionController::class, 'pin'])->name('dashboards.pin');
    Route::patch('dashboards/{dashboard}/appearance', [DashboardActionController::class, 'appearance'])->name('dashboards.appearance');
    Route::post('dashboards/{dashboard}/retry', [DashboardActionController::class, 'retry'])
        ->middleware('throttle:dashboard-generation')
        ->name('dashboards.retry');
    Route::post('dashboards/{dashboard}/refinements', [DashboardRefinementController::class, 'store'])
        ->middleware(AnswerHelpQuestions::class)
        ->name('dashboards.refinements.store');
    Route::post('dashboards/{dashboard}/refinements/{refinement}/apply', [DashboardRefinementController::class, 'apply'])->name('dashboards.refinements.apply');
    Route::delete('dashboards/{dashboard}/refinements/{refinement}', [DashboardRefinementController::class, 'discard'])->name('dashboards.refinements.discard');
    Route::post('dashboards/{dashboard}/versions/{number}/restore', [DashboardRefinementController::class, 'restore'])
        ->whereNumber('number')
        ->name('dashboards.versions.restore');
    Route::get('datasets', [DatasetController::class, 'index'])->name('datasets.index');
    Route::post('datasets', [DatasetController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('datasets.store');
    Route::get('datasets/{dataset}', [DatasetController::class, 'show'])->name('datasets.show');
    Route::post('datasets/{dataset}/import', [DatasetController::class, 'import'])->name('datasets.import');
    Route::delete('datasets/{dataset}', [DatasetController::class, 'destroy'])->name('datasets.destroy');
    Route::patch('organizations/{tenant:slug}/select', SelectTenantController::class)
        ->name('tenants.select');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
