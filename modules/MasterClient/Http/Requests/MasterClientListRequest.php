<?php
declare(strict_types=1);

namespace Modules\MasterClient\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MasterClientListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query'    => 'nullable|string|max:100',
            'tp1_code' => 'nullable|string|max:50',
            'tp2_code' => 'nullable|string|max:50',
            'cit_code' => 'nullable|string|max:50',
            'has_cep'  => 'nullable|string|in:true,false,1,0',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page'     => 'nullable|integer|min:1',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('per_page')) {
            $this->merge([
                'per_page' => filter_var($this->input('per_page'), FILTER_VALIDATE_INT) ?: null,
            ]);
        }
        if ($this->has('page')) {
            $this->merge([
                'page' => filter_var($this->input('page'), FILTER_VALIDATE_INT) ?: null,
            ]);
        }
    }
}
