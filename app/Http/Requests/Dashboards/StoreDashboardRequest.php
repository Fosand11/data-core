<?php

namespace App\Http\Requests\Dashboards;

use App\Domain\Dashboards\Enums\DatasetStatus;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Dashboard::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:10', 'max:2000'],
            // Optional: which uploaded dataset to ask about (none means the demo data). It
            // must belong to the current organization and be ready; never trusted otherwise.
            'dataset_id' => ['nullable', 'uuid', Rule::exists('datasets', 'id')->where(
                fn ($query) => $query->where('tenant_id', app(CurrentTenant::class)->id())->where('status', DatasetStatus::Ready->value),
            )],
            'tenant_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt.required' => 'Describe el dashboard que necesitas.',
            'prompt.min' => 'La solicitud debe tener al menos 10 caracteres.',
            'prompt.max' => 'La solicitud no puede superar los 2000 caracteres.',
            'dataset_id.exists' => 'Ese conjunto de datos no está disponible.',
            'tenant_id.prohibited' => 'La organización se obtiene de tu sesión y no puede enviarse manualmente.',
        ];
    }
}
