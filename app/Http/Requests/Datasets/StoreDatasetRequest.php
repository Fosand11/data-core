<?php

namespace App\Http\Requests\Datasets;

use App\Domain\Dashboards\Models\Dataset;
use Illuminate\Foundation\Http\FormRequest;

class StoreDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Dataset::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The extension is what decides the format; browsers report CSV MIME types inconsistently.
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:'.(int) config('datasets.max_file_kb')],
            'name' => ['nullable', 'string', 'max:120'],
            'tenant_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = round((int) config('datasets.max_file_kb') / 1024, 1);

        return [
            'file.required' => 'Elige un archivo CSV o Excel (.xlsx).',
            'file.file' => 'Elige un archivo CSV o Excel (.xlsx).',
            'file.uploaded' => "No se pudo subir el archivo. El tamaño máximo es {$maxMb} MB.",
            'file.max' => "El archivo supera el máximo de {$maxMb} MB.",
            'file.extensions' => 'Solo se aceptan archivos CSV o Excel (.xlsx). Si tu archivo es .xls, guárdalo como .xlsx o como CSV.',
            'name.max' => 'El nombre no puede superar los 120 caracteres.',
            'tenant_id.prohibited' => 'La organización se obtiene de tu sesión y no puede enviarse manualmente.',
        ];
    }
}
