<?php

namespace App\Http\Requests\Credits;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class ApplyUnapplyCreditsSearchRequest extends FormRequest
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
		$searched = Sanitize::input($this->input('searched'));
		$credit_id = Sanitize::input($this->input('credit_id'));
		$applied_ids = Sanitize::recursive($this->input('applied_ids'));
		$paid_ids = Sanitize::recursive($this->input('fetched_and_removed_ids'));

		$this->merge([
			'company_id'		=>	$company_id,
			'searched'			=>	$searched,
			'credit_id'			=>	$credit_id,
			'applied_ids'		=>	$applied_ids,
			'paid_ids'			=>	$paid_ids
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
			'searched'		=>	'sometimes',
			'applied_ids'	=>	'sometimes',
			'paid_ids'		=>	'sometimes',
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
