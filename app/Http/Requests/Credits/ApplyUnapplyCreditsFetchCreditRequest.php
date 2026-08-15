<?php

namespace App\Http\Requests\Credits;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class ApplyUnapplyCreditsFetchCreditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	#[Override]
	protected function prepareForValidation(){

		$company_id = Sanitize::input($this->input('company_id'));
		$credit_id = Sanitize::input($this->input('credit_id'));

		$this->merge([
			'company_id'	=>	$company_id,
			'credit_id'		=>	$credit_id
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
            'company_id'	=>	'required',
			'credit_id'		=>	['required', Rule::exists('credits', 'id')->where('company_id', $this->input('company_id'))],
        ];
    }

	public function messages(): array {

        return [
            'credit_id.required'	=> 'Invalid credit selected',
            'credit_id.exists'		=> 'Invalid credit selected'
        ];

    }
}
