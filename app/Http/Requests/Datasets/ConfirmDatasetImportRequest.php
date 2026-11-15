<?php

namespace App\Http\Requests\Datasets;

use App\Domain\Dashboards\Enums\DatasetColumnType;
use App\Domain\Dashboards\Models\Dataset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmDatasetImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dataset = $this->route('dataset');

        return $dataset instanceof Dataset && $this->user()?->can('update', $dataset) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'columns' => ['required', 'array', 'min:1', 'max:'.(int) config('datasets.max_columns')],
            'columns.*.id' => ['required', 'uuid'],
            'columns.*.label' => ['nullable', 'string', 'max:255'],
            'columns.*.type' => ['nullable', Rule::enum(DatasetColumnType::class)],
            'columns.*.included' => ['required', 'boolean'],
            'tenant_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre a tus datos.',
            'name.max' => 'El nombre no puede superar los 120 caracteres.',
            'columns.required' => 'Selecciona al menos una columna para importar.',
            'columns.*.type.enum' => 'Hay una columna con un tipo no válido.',
            'tenant_id.prohibited' => 'La organización se obtiene de tu sesión y no puede enviarse manualmente.',
        ];
    }
}
