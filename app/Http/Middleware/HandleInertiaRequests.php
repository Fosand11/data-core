<?php

namespace App\Http\Middleware;

use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Tenancy\CurrentTenant;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            // Lazy and tenant-scoped: resolved when the response renders, after the
            // current tenant is known, and skipped by partial reloads that exclude it.
            'recentDashboards' => fn (): array => $this->sidebarDashboards($request, pinned: false),
            'pinnedDashboards' => fn (): array => $this->sidebarDashboards($request, pinned: true),
            'flash' => [
                'status' => fn (): ?string => $request->session()->get('status'),
            ],
        ]);
    }

    /**
     * @return list<array{id: string, title: string, status: string}>
     */
    private function sidebarDashboards(Request $request, bool $pinned): array
    {
        $user = $request->user();
        $tenant = app(CurrentTenant::class)->get();

        if ($user === null || $tenant === null) {
            return [];
        }

        $query = Dashboard::query()->forTenant($tenant);

        if ($pinned) {
            $query->whereHas('pinnedBy', fn ($pins) => $pins->where('users.id', $user->getKey()))
                ->latest('updated_at');
        } else {
            $query->latest();
        }

        return $query
            ->limit(5)
            ->get(['id', 'title', 'status'])
            ->map(fn (Dashboard $dashboard): array => [
                'id' => $dashboard->getKey(),
                'title' => $dashboard->title,
                'status' => $dashboard->status->value,
            ])
            ->all();
    }
}
