<?php

namespace App\Http\Requests\Dashboards;

use Illuminate\Foundation\Http\FormRequest;

class StoreRefinementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('refine', $this->route('dashboard')) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'instruction' => ['required', 'string', 'min:5', 'max:500'],
            'tenant_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'instruction.required' => 'Cuéntame qué quieres cambiar.',
            'instruction.min' => 'Describe el cambio con al menos 5 caracteres.',
            'instruction.max' => 'El cambio no puede superar los 500 caracteres.',
        ];
    }
}
