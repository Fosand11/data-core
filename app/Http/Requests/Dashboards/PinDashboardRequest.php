<?php

namespace App\Http\Requests\Dashboards;

use Illuminate\Foundation\Http\FormRequest;

class PinDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pin', $this->route('dashboard')) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pinned' => ['required', 'boolean'],
        ];
    }
}
