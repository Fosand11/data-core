<?php

namespace App\Http\Controllers;

use App\Domain\Dashboards\Actions\ApplyDashboardRefinement;
use App\Domain\Dashboards\Actions\DiscardDashboardRefinement;
use App\Domain\Dashboards\Actions\RequestDashboardRefinement;
use App\Domain\Dashboards\Actions\RestoreDashboardVersion;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Models\DashboardGeneration;
use App\Http\Requests\Dashboards\StoreRefinementRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Propose, apply, discard and undo changes to an existing dashboard. Rules and
 * authorization live in the actions and the Policy.
 */
class DashboardRefinementController extends Controller
{
    public function store(StoreRefinementRequest $request, Dashboard $dashboard, RequestDashboardRefinement $refine): RedirectResponse
    {
        $refine->execute($request->user(), $dashboard, $request->validated('instruction'));

        return back()->with('status', 'Preparando los cambios…');
    }

    public function apply(Request $request, Dashboard $dashboard, DashboardGeneration $refinement, ApplyDashboardRefinement $apply): RedirectResponse
    {
        $this->assertBelongsTo($dashboard, $refinement);
        $apply->execute($request->user(), $dashboard, $refinement);

        return back()->with('status', 'Cambios aplicados. Puedes deshacerlos desde el historial.');
    }

    public function discard(Request $request, Dashboard $dashboard, DashboardGeneration $refinement, DiscardDashboardRefinement $discard): RedirectResponse
    {
        $this->assertBelongsTo($dashboard, $refinement);
        $discard->execute($request->user(), $dashboard, $refinement);

        return back()->with('status', 'Cambios descartados.');
    }

    public function restore(Request $request, Dashboard $dashboard, int $number, RestoreDashboardVersion $restore): RedirectResponse
    {
        $restore->execute($request->user(), $dashboard, $number);

        return back()->with('status', "Se restauró la versión {$number}.");
    }

    private function assertBelongsTo(Dashboard $dashboard, DashboardGeneration $refinement): void
    {
        abort_unless($refinement->dashboard_id === $dashboard->getKey(), 404);
    }
}
