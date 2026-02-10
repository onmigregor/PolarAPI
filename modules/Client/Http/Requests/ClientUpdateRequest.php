<?php

namespace Modules\Client\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $client = $this->route('client');
        $clientId = $client instanceof \Modules\Client\Models\Client ? $client->id : $client;

        return [
            'code' => 'required|string|max:255|unique:clients,code,' . $clientId,
            'name' => 'required|string|max:255|unique:clients,name,' . $clientId,
            'rif' => 'required|string|max:20',
            'description' => 'sometimes|string|nullable',
            'fiscal_address' => 'sometimes|string|nullable',
            'region_id' => 'required|exists:regions,id',
            'db_name' => 'required|string|max:255|unique:clients,db_name,' . $clientId,
        ];
    }
}
