<?php

namespace App\Http\Controllers;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Dashboards\Actions\StartDashboardGeneration;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Requests\Dashboards\StoreDashboardRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardManagementController extends Controller
{
    public function __construct(
        private readonly CurrentTenant $currentTenant,
        private readonly DashboardGenerator $generator,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Dashboard::class);
        $tenant = $this->currentTenant->require();

        $dashboards = $tenant->dashboards()
            ->with('creator:id,name')
            ->latest()
            ->get()
            ->map(fn (Dashboard $dashboard): array => $this->summary($dashboard));

        return Inertia::render('dashboards/Index', [
            ...$this->navigationProps($request, $tenant),
            'dashboards' => $dashboards,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Dashboard::class);
        $tenant = $this->currentTenant->require();
        $prompt = $request->query('prompt');

        return Inertia::render('dashboards/Create', [
            ...$this->navigationProps($request, $tenant),
            'initialPrompt' => is_string($prompt) && mb_strlen($prompt) <= 2000 ? trim($prompt) : '',
            'provider' => $this->generator->provider(),
        ]);
    }

    public function store(StoreDashboardRequest $request, StartDashboardGeneration $startGeneration): RedirectResponse
    {
        $dashboard = $startGeneration->execute(
            $request->user(),
            $request->validated('prompt'),
        );

        return to_route('dashboards.show', $dashboard);
    }

    public function show(Request $request, Dashboard $dashboard): Response
    {
        Gate::authorize('view', $dashboard);
        $tenant = $this->currentTenant->require();
        $dashboard->load(['creator:id,name', 'generations' => fn ($query) => $query->latest()]);
        $latestGeneration = $dashboard->generations->first();

        return Inertia::render('dashboards/Show', [
            ...$this->navigationProps($request, $tenant),
            'dashboard' => [
                ...$this->summary($dashboard),
                'description' => $dashboard->description,
                'original_prompt' => $dashboard->original_prompt,
                'provider' => $dashboard->provider,
                'spec_version' => $dashboard->spec_version,
                'dashboard_spec' => $dashboard->dashboard_spec,
                'error_message' => $dashboard->error_message,
                'generation_started_at' => $dashboard->generation_started_at?->toIso8601String(),
                'generation_completed_at' => $dashboard->generation_completed_at?->toIso8601String(),
                'generation' => $latestGeneration === null ? null : [
                    'id' => $latestGeneration->getKey(),
                    'status' => $latestGeneration->status->value,
                    'provider' => $latestGeneration->provider,
                    'started_at' => $latestGeneration->started_at?->toIso8601String(),
                    'completed_at' => $latestGeneration->completed_at?->toIso8601String(),
                    'error_message' => $latestGeneration->error_message,
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Dashboard $dashboard): array
    {
        return [
            'id' => $dashboard->getKey(),
            'title' => $dashboard->title,
            'status' => $dashboard->status->value,
            'created_at' => $dashboard->created_at->toIso8601String(),
            'author' => $dashboard->creator === null ? null : [
                'name' => $dashboard->creator->name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function navigationProps(Request $request, Tenant $tenant): array
    {
        $tenants = $request->user()
            ->tenants()
            ->where('tenants.status', TenantStatus::Active->value)
            ->orderBy('tenants.name')
            ->get()
            ->map(fn (Tenant $organization): array => [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'role' => $organization->pivot->role,
            ]);

        return [
            'currentTenant' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'tenants' => $tenants,
        ];
    }
}
