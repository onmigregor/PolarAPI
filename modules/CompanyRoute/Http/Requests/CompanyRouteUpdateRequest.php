<?php

namespace Modules\CompanyRoute\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\CompanyRoute\Models\CompanyRoute;

class CompanyRouteUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyRoute = $this->route('company_route');
        $companyRouteId = $companyRoute instanceof CompanyRoute ? $companyRoute->id : $companyRoute;

        return [
            'code' => 'sometimes|required|string|max:255|unique:company_routes,code,' . $companyRouteId,
            'name' => 'sometimes|required|string|max:255|unique:company_routes,name,' . $companyRouteId,
            'route_name' => 'nullable|string|max:255',
            'rif' => 'sometimes|required|string|max:20',
            'description' => 'sometimes|string|nullable',
            'fiscal_address' => 'sometimes|string|nullable',
            'region_id' => 'sometimes|required|exists:regions,id',
            'db_name' => 'sometimes|required|string|max:255|unique:company_routes,db_name,' . $companyRouteId,
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
