<?php

namespace Modules\Report\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\CompanyRoute\Models\CompanyRoute;

class ExportSalesCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'route_code' => ['nullable', 'string', Rule::exists(CompanyRoute::class, 'code')],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'La fecha es obligatoria.',
            'date.date_format' => 'La fecha debe tener el formato YYYY-MM-DD.',
            'route_code.exists' => 'El código de ruta no existe.',
        ];
    }
}
