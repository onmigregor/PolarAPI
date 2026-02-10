<?php

namespace Modules\Client\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:255|unique:clients,code',
            'name' => 'required|string|max:255|unique:clients,name',
            'rif' => 'required|string|max:20',
            'description' => 'sometimes|string|nullable',
            'fiscal_address' => 'required|string|max:255',
            'region_id' => 'required|exists:regions,id',
            'db_name' => 'required|string|max:255|unique:clients,db_name',
        ];
    }
}
