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
            'date'       => ['required_without:start_date', 'nullable', 'date', 'date_format:Y-m-d'],
            'start_date' => ['required_without:date', 'nullable', 'date', 'date_format:Y-m-d'],
            'end_date'   => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'route_code' => ['nullable', 'string', Rule::exists(CompanyRoute::class, 'code')],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required_without'       => 'Debe proporcionar una fecha o un rango (start_date).',
            'start_date.required_without' => 'Debe proporcionar una fecha de inicio si no usa el parámetro date.',
            'end_date.after_or_equal'     => 'La fecha final debe ser posterior o igual a la inicial.',
            'route_code.exists'           => 'El código de ruta no existe.',
        ];
    }
}
