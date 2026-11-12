<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Ai\Data\DashboardSpec;
use App\Domain\Ai\Exceptions\InvalidDashboardSpecException;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use App\Domain\Dashboards\Enums\DashboardGenerationStatus;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use App\Domain\Dashboards\Models\DashboardVersion;
use App\Domain\Dashboards\Support\DashboardVersions;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class ApplyDashboardRefinement
{
    public function __construct(
        private DashboardSpecValidator $validator,
        private DashboardVersions $versions,
    ) {}

    public function execute(User $user, Dashboard $dashboard, DashboardGeneration $refinement): Dashboard
    {
        Gate::forUser($user)->authorize('refine', $dashboard);

        DB::transaction(function () use ($user, $dashboard, $refinement): void {
            $locked = Dashboard::query()->lockForUpdate()->findOrFail($dashboard->getKey());
            $proposal = DashboardGeneration::query()->lockForUpdate()->findOrFail($refinement->getKey());

            $this->assertApplicable($locked, $proposal);

            // The proposal comes from a model: validate it again before it becomes live.
            try {
                $spec = $this->validator->validate(DashboardSpec::fromArray($proposal->validated_spec))->toArray();
            } catch (InvalidDashboardSpecException) {
                throw ValidationException::withMessages([
                    'refinement' => 'La propuesta ya no supera la validación de seguridad y no se puede aplicar.',
                ]);
            }

            $this->versions->ensureInitial($locked);

            if ($this->versions->currentNumber($locked) !== $proposal->base_version) {
                throw ValidationException::withMessages([
                    'refinement' => 'El dashboard cambió desde que se propuso este ajuste. Pide el ajuste de nuevo.',
                ]);
            }

            $this->versions->record($locked, $spec, DashboardVersion::SOURCE_REFINED, $proposal->prompt, $user->getKey());
            $this->versions->publish($locked, $spec);
            $proposal->update(['applied_at' => now()]);
        });

        return $dashboard->refresh();
    }

    private function assertApplicable(Dashboard $dashboard, DashboardGeneration $proposal): void
    {
        $applicable = $proposal->dashboard_id === $dashboard->getKey()
            && $proposal->isRefinement()
            && $proposal->status === DashboardGenerationStatus::Completed
            && $proposal->applied_at === null
            && $proposal->discarded_at === null
            && is_array($proposal->validated_spec);

        if (! $applicable) {
            throw ValidationException::withMessages([
                'refinement' => 'Este ajuste ya no está disponible.',
            ]);
        }
    }
}
