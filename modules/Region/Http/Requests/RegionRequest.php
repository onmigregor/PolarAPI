<?php

namespace Modules\Region\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('region') ? $this->route('region')->id : null;

        return [
            'citCode' => 'required|string|max:255|unique:regions,citCode,' . $id,
            'citName' => 'required|string|max:255',
            'staCode' => 'required|string|max:255',
        ];
    }
}
