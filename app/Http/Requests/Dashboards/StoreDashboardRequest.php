<?php

namespace App\Http\Requests\Dashboards;

use App\Domain\Dashboards\Models\Dashboard;
use Illuminate\Foundation\Http\FormRequest;

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
            'tenant_id.prohibited' => 'La organización se obtiene de tu sesión y no puede enviarse manualmente.',
        ];
    }
}
