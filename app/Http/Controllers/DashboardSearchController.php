<?php

namespace App\Http\Controllers;

use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Tenancy\CurrentTenant;
use App\Http\Requests\Dashboards\SearchDashboardsRequest;
use App\Http\Resources\DashboardSummaryResource;
use Illuminate\Http\JsonResponse;

/**
 * Backs the command palette: finds dashboards of the current organization by
 * title or original prompt. An empty term returns the most recent ones.
 */
class DashboardSearchController extends Controller
{
    private const LIMIT = 8;

    public function __invoke(SearchDashboardsRequest $request, CurrentTenant $currentTenant): JsonResponse
    {
        $term = trim((string) $request->validated('q', ''));

        $query = Dashboard::query()
            ->forTenant($currentTenant->require())
            ->withPinnedState($request->user())
            ->with('creator:id,name')
            ->latest();

        if ($term !== '') {
            $query->search($term);
        }

        return response()->json([
            'data' => DashboardSummaryResource::collection($query->limit(self::LIMIT)->get())->resolve(),
        ]);
    }
}
