<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Models\Dashboard;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DuplicateDashboard
{
    private const SUFFIX = ' (copia)';

    /**
     * Copies an already generated dashboard inside its own organization. The
     * copy is independent: it keeps the validated spec but no generation
     * history, so it can be renamed or deleted without touching the original.
     */
    public function execute(User $user, Dashboard $source): Dashboard
    {
        Gate::forUser($user)->authorize('duplicate', $source);

        if ($source->status !== DashboardStatus::Ready || ! is_array($source->dashboard_spec)) {
            throw ValidationException::withMessages([
                'dashboard' => 'Solo se pueden duplicar dashboards que ya están listos.',
            ]);
        }

        return $source->tenant->dashboards()->create([
            'created_by' => $user->getKey(),
            'title' => Str::limit($source->title, 120 - mb_strlen(self::SUFFIX), '').self::SUFFIX,
            'description' => $source->description,
            'original_prompt' => $source->original_prompt,
            'status' => DashboardStatus::Ready,
            'spec_version' => $source->spec_version,
            'dashboard_spec' => $source->dashboard_spec,
            'provider' => $source->provider,
            'generation_completed_at' => now(),
        ]);
    }
}
