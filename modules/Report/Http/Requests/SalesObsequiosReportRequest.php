<?php

namespace Modules\Report\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesObsequiosReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id'       => 'nullable|integer',
            'start_date'      => 'nullable|date|before_or_equal:end_date',
            'end_date'        => 'nullable|date|before_or_equal:today',
            'sftp_start_date' => 'nullable|date',
            'sftp_end_date'   => 'nullable|date',
            'only_pending'    => 'nullable|boolean',
            'id_venta'        => 'nullable|numeric|digits_between:1,9',
            'cliente'         => 'nullable|string|max:100',
            'page'            => 'nullable|integer|min:1',
            'per_page'        => 'nullable|integer|min:1|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.before_or_equal' => 'La fecha de inicio debe ser menor o igual a la fecha final.',
            'end_date.before_or_equal'   => 'La fecha final no puede ser mayor a la fecha actual.',
            'id_venta.numeric'           => 'El ID de venta debe contener solo números.',
            'id_venta.digits_between'    => 'El ID de venta no puede exceder los 9 dígitos.',
        ];
    }
}
