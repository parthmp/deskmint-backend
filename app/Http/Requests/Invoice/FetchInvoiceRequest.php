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
		$timezone = (string) Sanitize::input($this->input('timezone'));

		$this->merge([
			'company_id'				=>		$company_id,
			'timezone'					=>		$timezone,
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
            'timezone' => [
							'required',
							'string',
							function ($attribute, $value, $fail) {
								try {
									new \DateTimeZone($value);
								} catch (\Exception $e) {
									$fail("The {$attribute} must be a valid timezone.");
								}
							},
						]
        ];
    }
}