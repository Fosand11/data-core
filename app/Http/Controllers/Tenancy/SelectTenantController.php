<?php

namespace App\Http\Controllers\Tenancy;

use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\SelectTenantRequest;
use Illuminate\Http\RedirectResponse;

class SelectTenantController extends Controller
{
    public function __invoke(
        SelectTenantRequest $request,
        Tenant $tenant,
        CurrentTenant $currentTenant,
    ): RedirectResponse {
        $request->session()->put((string) config('tenancy.session_key'), $tenant->getKey());
        $currentTenant->set($tenant);

        return to_route('dashboard');
    }
}
