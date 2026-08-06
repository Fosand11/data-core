<?php

namespace App\Http\Requests\Tenancy;

use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class SelectTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');
        $user = $this->user();

        if (! $tenant instanceof Tenant || $user === null) {
            return false;
        }

        return $user->tenants()
            ->whereKey($tenant->getKey())
            ->where('tenants.status', TenantStatus::Active->value)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
