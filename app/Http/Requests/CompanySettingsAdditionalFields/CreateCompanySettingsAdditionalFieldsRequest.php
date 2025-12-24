<?php

namespace App\Http\Requests\CompanySettingsAdditionalFields;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateCompanySettingsAdditionalFieldsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
			'company_id' 			=> 	'sometimes',
            'all_fields'			=>	'required|array',
			'all_fields.*.label'	=>	'required',
			'all_fields.*.value'	=>	'sometimes',
			'all_fields.*.id'		=>	'sometimes'
        ];
    }
}
