<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentTenant
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $sessionKey = (string) config('tenancy.session_key');

        if ($user === null) {
            $this->currentTenant->forget();

            return $next($request);
        }

        $selectedId = $request->session()->get($sessionKey);
        $tenant = null;

        if (is_string($selectedId)) {
            $tenant = $user->tenants()
                ->whereKey($selectedId)
                ->where('tenants.status', TenantStatus::Active->value)
                ->first();
        }

        $tenant ??= $user->tenants()
            ->where('tenants.status', TenantStatus::Active->value)
            ->orderBy('tenants.name')
            ->first();

        if ($tenant instanceof Tenant) {
            $this->currentTenant->set($tenant);
            $request->session()->put($sessionKey, $tenant->getKey());
        } else {
            $this->currentTenant->forget();
            $request->session()->forget($sessionKey);
        }

        return $next($request);
    }
}
