<?php

namespace App\Http\Requests\Dashboards;

use App\Domain\Dashboards\Support\ChartPalettes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDashboardAppearanceRequest extends FormRequest
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
            'palette' => ['required', 'string', Rule::in(ChartPalettes::ids())],
            'colors' => ['present', 'array', 'max:12'],
            'colors.*' => ['integer', 'between:0,'.(ChartPalettes::SLOTS - 1)],
            'tenant_id' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_keys((array) $this->input('colors', [])) as $key) {
                    if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', (string) $key) !== 1) {
                        $validator->errors()->add('colors', 'Hay una gráfica con un identificador no válido.');

                        return;
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'palette.required' => 'Elige una paleta.',
            'palette.in' => 'Esa paleta no existe.',
            'colors.*.between' => 'Hay un color que no pertenece a la paleta.',
            'colors.*.integer' => 'Hay un color que no pertenece a la paleta.',
            'tenant_id.prohibited' => 'La organización se obtiene de tu sesión y no puede enviarse manualmente.',
        ];
    }
}
