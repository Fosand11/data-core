<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardVersion;
use App\Domain\Dashboards\Support\DashboardVersions;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class RestoreDashboardVersion
{
    public function __construct(private DashboardVersions $versions) {}

    /**
     * Makes an earlier version live again by recording it as a new version, so
     * the history only ever grows and a restore can itself be undone.
     */
    public function execute(User $user, Dashboard $dashboard, int $number): Dashboard
    {
        Gate::forUser($user)->authorize('refine', $dashboard);

        DB::transaction(function () use ($user, $dashboard, $number): void {
            $locked = Dashboard::query()->lockForUpdate()->findOrFail($dashboard->getKey());

            if ($locked->status !== DashboardStatus::Ready) {
                throw ValidationException::withMessages(['version' => 'Solo se pueden restaurar versiones de dashboards listos.']);
            }

            $this->versions->ensureInitial($locked);
            $target = $locked->versions()->where('number', $number)->first();

            if ($target === null) {
                throw ValidationException::withMessages(['version' => 'Esa versión no existe.']);
            }

            if ($number === $this->versions->currentNumber($locked)) {
                throw ValidationException::withMessages(['version' => 'Esa ya es la versión actual.']);
            }

            $this->versions->record($locked, $target->spec, DashboardVersion::SOURCE_RESTORED, "Se restauró la versión {$number}.", $user->getKey());
            $this->versions->publish($locked, $target->spec);

            // Any pending proposal was based on a version that is no longer current.
            $locked->generations()->unresolvedRefinements()->update(['discarded_at' => now()]);
        });

        return $dashboard->refresh();
    }
}
