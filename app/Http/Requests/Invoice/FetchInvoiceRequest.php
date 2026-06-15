<?php

namespace App\Http\Requests\Invoice;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class FetchInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	#[Override]
	protected function prepareForValidation()
	{
		$company_id = (int) Sanitize::input($this->input('company_id'));
		$timezone_offset_minutes = (int) Sanitize::input($this->input('timezone_offset_minutes'));

		$this->merge([
			'company_id'				=>		$company_id,
			'timezone_offset_minutes'	=>		$timezone_offset_minutes,
		]);
	}

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_id'				=>	'required',
            'timezone_offset_minutes'	=>	'required|numeric',
        ];
    }
}
