<?php

namespace App\Http\Requests;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class VoidTransactionRequest extends FormRequest
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
		$id = Sanitize::input($this->input('id'));

		$this->merge([
			'company_id'	=>	$company_id,
			'id'			=>	$id
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
            'company_id' =>	'required|numeric',
            'id' 		 =>	'required|numeric'
        ];
    }
}
