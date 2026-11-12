<?php

namespace App\Http\Requests\Dashboards;

use App\Domain\Dashboards\Models\Dashboard;
use Illuminate\Foundation\Http\FormRequest;

class SearchDashboardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Dashboard::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'tenant_id' => ['prohibited'],
        ];
    }
}
