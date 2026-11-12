<?php

namespace App\Http\Requests\Dashboards;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('dashboard')) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'tenant_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Escribe un título.',
            'title.max' => 'El título no puede superar los 120 caracteres.',
            'description.max' => 'La descripción no puede superar los 500 caracteres.',
        ];
    }
}
