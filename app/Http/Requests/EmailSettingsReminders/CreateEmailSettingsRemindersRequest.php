<?php

namespace App\Http\Requests\EmailSettingsReminders;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateEmailSettingsRemindersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	/**
	 * prepareForValidation function
	 *
	 * @return void
	 */
	protected function prepareForValidation(){
		
		$company_id = (int) Sanitize::input($this->input('company_id'));

		$send_n_times = 0;
		if($this->filled('send_n_times')){
			$send_n_times = Sanitize::input($this->input('send_n_times'));
		}

		$days_gap = 0;

		if($this->filled('days_gap')){
			$days_gap = Sanitize::input($this->input('days_gap'));
		}

		$this->merge([
			'company_id'	=>	$company_id,
			'send_n_times'	=>	$send_n_times,
			'days_gap'		=>	$days_gap
		]);

	}

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_id'	=>	'required',
            'send_n_times'	=>	'sometimes',
            'days_gap'		=>	'sometimes'
        ];
    }
}
