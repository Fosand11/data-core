<?php

namespace App\Http\Controllers;

use App\Domain\Dashboards\Actions\DuplicateDashboard;
use App\Domain\Dashboards\Actions\RetryDashboardGeneration;
use App\Domain\Dashboards\Actions\SetDashboardPin;
use App\Domain\Dashboards\Models\Dashboard;
use App\Http\Requests\Dashboards\PinDashboardRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Single-purpose actions on an existing dashboard. Authorization and business
 * rules live in the Policy and the actions; this class only translates HTTP.
 */
class DashboardActionController extends Controller
{
    public function duplicate(Request $request, Dashboard $dashboard, DuplicateDashboard $duplicate): RedirectResponse
    {
        $copy = $duplicate->execute($request->user(), $dashboard);

        return to_route('dashboards.show', $copy)->with('status', 'Dashboard duplicado.');
    }

    public function pin(PinDashboardRequest $request, Dashboard $dashboard, SetDashboardPin $setPin): RedirectResponse
    {
        $pinned = $request->boolean('pinned');
        $setPin->execute($request->user(), $dashboard, $pinned);

        return back()->with('status', $pinned ? 'Fijado en tu barra lateral.' : 'Ya no está fijado.');
    }

    public function retry(Request $request, Dashboard $dashboard, RetryDashboardGeneration $retry): RedirectResponse
    {
        $retry->execute($request->user(), $dashboard);

        return back()->with('status', 'Generando de nuevo…');
    }
}
