<?php

namespace Modules\CompanyRoute\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyRouteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:255|unique:company_routes,code',
            'name' => 'required|string|max:255|unique:company_routes,name',
            'route_name' => 'nullable|string|max:255',
            'rif' => 'required|string|max:20',
            'description' => 'sometimes|string|nullable',
            'fiscal_address' => 'required|string|max:255',
            'region_id' => 'required|exists:regions,id',
            'db_name' => 'required|string|max:255|unique:company_routes,db_name',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('route_name')) {
            $this->merge([
                'route_name' => $this->route_name ? strtoupper($this->route_name) : null,
            ]);
        }
    }
}
