<?php

namespace Modules\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Region\Models\Region;
use Modules\CompanyRoute\Models\CompanyRoute;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'region_ids' => ['nullable', 'array'],
            'region_ids.*' => ['integer', Rule::exists(Region::class, 'id')],
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer', Rule::exists(CompanyRoute::class, 'id')],
            'product_skus' => ['nullable', 'array'],
            'product_skus.*' => ['string'],
            'routes' => ['nullable', 'array'],
            'routes.*' => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'end_date.required' => 'La fecha de fin es obligatoria.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
        ];
    }
}
