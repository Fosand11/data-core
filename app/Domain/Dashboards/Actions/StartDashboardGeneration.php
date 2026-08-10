<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Ai\Contracts\DashboardGenerator;
use App\Domain\Dashboards\Enums\DashboardGenerationStatus;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Jobs\GenerateDashboardJob;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Tenancy\CurrentTenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final readonly class StartDashboardGeneration
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private DashboardGenerator $generator,
    ) {}

    public function execute(User $user, string $prompt): Dashboard
    {
        Gate::forUser($user)->authorize('create', Dashboard::class);

        $tenant = $this->currentTenant->require();
        $normalizedPrompt = trim($prompt);
        $provider = $this->generator->provider();

        [$dashboard, $generation] = DB::transaction(function () use ($tenant, $user, $normalizedPrompt, $provider): array {
            $dashboard = $tenant->dashboards()->create([
                'created_by' => $user->getKey(),
                'title' => Str::limit($normalizedPrompt, 120, ''),
                'description' => null,
                'original_prompt' => $normalizedPrompt,
                'status' => DashboardStatus::Queued,
                'provider' => $provider,
            ]);

            $generation = $dashboard->generations()->create([
                'tenant_id' => $tenant->getKey(),
                'requested_by' => $user->getKey(),
                'provider' => $provider,
                'status' => DashboardGenerationStatus::Queued,
                'prompt' => $normalizedPrompt,
            ]);

            return [$dashboard, $generation];
        });

        GenerateDashboardJob::dispatch($generation->getKey())->afterCommit();

        return $dashboard;
    }
}
