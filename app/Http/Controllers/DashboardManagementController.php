<?php

namespace App\Http\Controllers;

use App\Domain\Ai\Catalogs\DemoSalesDatasetCatalog;
use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Ai\Contracts\ModelAwareDashboardGenerator;
use App\Domain\Ai\Data\DashboardSpec;
use App\Domain\Ai\Exceptions\InvalidDashboardSpecException;
use App\Domain\Dashboards\Actions\DeleteDashboard;
use App\Domain\Dashboards\Actions\StartDashboardGeneration;
use App\Domain\Dashboards\Actions\UpdateDashboardDetails;
use App\Domain\Dashboards\Enums\DashboardGenerationKind;
use App\Domain\Dashboards\Enums\DashboardGenerationStatus;
use App\Domain\Dashboards\Enums\DashboardPeriod;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Dashboards\Queries\DashboardQueryExecutor;
use App\Domain\Dashboards\Support\DashboardVersions;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Requests\Dashboards\StoreDashboardRequest;
use App\Http\Requests\Dashboards\UpdateDashboardRequest;
use App\Http\Resources\DashboardSummaryResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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

        $dashboards = DashboardSummaryResource::collection(
            $tenant->dashboards()
                ->withPinnedState($request->user())
                ->with('creator:id,name')
                ->orderByDesc('is_pinned')
                ->latest()
                ->get(),
        )->resolve();

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
            'providerModel' => $this->generator instanceof ModelAwareDashboardGenerator
                ? $this->generator->model()
                : null,
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

    public function show(Request $request, Dashboard $dashboard, DashboardQueryExecutor $executor, DemoSalesDatasetCatalog $catalog, DashboardVersions $versions): Response
    {
        Gate::authorize('view', $dashboard);
        $tenant = $this->currentTenant->require();
        $period = DashboardPeriod::tryFrom((string) $request->query('period', '')) ?? DashboardPeriod::Spec;
        $dashboard->load(['creator:id,name', 'generations' => fn ($query) => $query->where('kind', DashboardGenerationKind::Create->value)->latest()]);
        $dashboard->loadExists(['pinnedBy as is_pinned' => fn ($pins) => $pins->where('users.id', $request->user()->getKey())]);
        $latestGeneration = $dashboard->generations->first();
        $refinement = Gate::allows('refine', $dashboard)
            ? $dashboard->generations()->unresolvedRefinements()->latest()->first()
            : null;

        return Inertia::render('dashboards/Show', [
            ...$this->navigationProps($request, $tenant),
            'dashboard' => [
                ...DashboardSummaryResource::make($dashboard)->resolve(),
                'description' => $dashboard->description,
                'original_prompt' => $dashboard->original_prompt,
                'provider' => $dashboard->provider,
                'spec_version' => $dashboard->spec_version,
                'dashboard_spec' => $dashboard->dashboard_spec,
                'can' => [
                    'update' => Gate::allows('update', $dashboard),
                    'duplicate' => Gate::allows('duplicate', $dashboard),
                    'delete' => Gate::allows('delete', $dashboard),
                    'retry' => Gate::allows('retry', $dashboard) && $dashboard->canBeRetried(),
                    'refine' => Gate::allows('refine', $dashboard) && $dashboard->status === DashboardStatus::Ready,
                ],
                'error_message' => $dashboard->error_message,
                'generation_started_at' => $dashboard->generation_started_at?->toIso8601String(),
                'generation_completed_at' => $dashboard->generation_completed_at?->toIso8601String(),
                'generation' => $latestGeneration === null ? null : [
                    'id' => $latestGeneration->getKey(),
                    'status' => $latestGeneration->status->value,
                    'provider' => $latestGeneration->provider,
                    'model' => $latestGeneration->model,
                    'started_at' => $latestGeneration->started_at?->toIso8601String(),
                    'completed_at' => $latestGeneration->completed_at?->toIso8601String(),
                    'error_message' => $latestGeneration->error_message,
                ],
            ],
            // Lazy: evaluated on full visits and when requested by partial reloads.
            'period' => $this->periodPayload($period, $catalog),
            'refinement' => $refinement === null ? null : $this->refinementPayload($refinement, $versions->currentNumber($dashboard)),
            'versions' => $this->versionsPayload($dashboard),
            'visualizationData' => fn (): ?array => $this->visualizationData($dashboard, $executor, $period),
            'proposalData' => fn (): ?array => $this->proposalData($refinement, $executor, $period),
        ]);
    }

    public function update(UpdateDashboardRequest $request, Dashboard $dashboard, UpdateDashboardDetails $update): RedirectResponse
    {
        $update->execute($request->user(), $dashboard, $request->validated('title'), $request->validated('description'));

        return back()->with('status', 'Cambios guardados.');
    }

    public function destroy(Request $request, Dashboard $dashboard, DeleteDashboard $delete): RedirectResponse
    {
        $delete->execute($request->user(), $dashboard);

        return to_route('dashboards.index')->with('status', 'Dashboard eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function refinementPayload(DashboardGeneration $refinement, int $currentVersion): array
    {
        $completed = $refinement->status === DashboardGenerationStatus::Completed;

        return [
            'id' => $refinement->getKey(),
            'status' => $refinement->status->value,
            'instruction' => $refinement->prompt,
            'error_message' => $refinement->error_message,
            'proposed_spec' => $completed ? $refinement->validated_spec : null,
            // The dashboard moved on since this proposal was made, so it can no longer be applied.
            'is_stale' => $refinement->base_version !== $currentVersion,
        ];
    }

    /**
     * Newest first, so the UI can show "undo" next to the latest entry.
     *
     * @return list<array<string, mixed>>
     */
    private function versionsPayload(Dashboard $dashboard): array
    {
        $versions = $dashboard->versions()->with('author:id,name')->get();
        $current = $versions->max('number');

        return $versions->sortByDesc('number')->values()->map(fn ($version): array => [
            'number' => $version->number,
            'source' => $version->source,
            'instruction' => $version->instruction,
            'author' => $version->author?->name,
            'created_at' => $version->created_at->toIso8601String(),
            'is_current' => $version->number === $current,
        ])->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function proposalData(?DashboardGeneration $refinement, DashboardQueryExecutor $executor, DashboardPeriod $period): ?array
    {
        if ($refinement === null || $refinement->status !== DashboardGenerationStatus::Completed || ! is_array($refinement->validated_spec)) {
            return null;
        }

        Gate::authorize('viewAny', DemoSale::class);

        try {
            return $executor->execute(DashboardSpec::fromArray($refinement->validated_spec), $period);
        } catch (InvalidDashboardSpecException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function periodPayload(DashboardPeriod $period, DemoSalesDatasetCatalog $catalog): array
    {
        $range = $period->range(CarbonImmutable::now());

        return [
            'value' => $period->value,
            'label' => $period->label(),
            'overrides_spec' => $period->overridesSpec(),
            'date_field' => $catalog->dateField(),
            // Inclusive dates for display; the query itself uses a half-open range.
            'range' => $range === null ? null : [
                'from' => $range[0]->toDateString(),
                'to' => $range[1]->subDay()->toDateString(),
            ],
            'options' => array_map(
                fn (DashboardPeriod $option): array => ['value' => $option->value, 'label' => $option->label()],
                DashboardPeriod::cases(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function visualizationData(Dashboard $dashboard, DashboardQueryExecutor $executor, DashboardPeriod $period): ?array
    {
        if ($dashboard->status !== DashboardStatus::Ready || ! is_array($dashboard->dashboard_spec)) {
            return null;
        }

        Gate::authorize('viewAny', DemoSale::class);

        try {
            return $executor->execute(DashboardSpec::fromArray($dashboard->dashboard_spec), $period);
        } catch (InvalidDashboardSpecException $exception) {
            Log::warning('Stored dashboard spec no longer passes validation.', [
                'dashboard_id' => $dashboard->getKey(),
            ]);

            return null;
        }
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
