<?php

namespace App\Http\Requests\Invoice;

use App\Enums\Invoices\ArchivedStatus;
use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class ChangeArchivedStatusRequest extends FormRequest
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

		$ids = Sanitize::recursive((array) $this->input('ids'));
		$archived = (int) Sanitize::input($this->input('archived'));

		$archived_status = ArchivedStatus::YES->value;
		if($archived === ArchivedStatus::NO->value){
			$archived_status = ArchivedStatus::NO->value;
		}

		$company_id = Sanitize::input($this->input('company_id'));

		$this->merge([
			'company_id'	=>	$company_id,
			'ids'			=>	$ids,
			'archived'		=>	$archived_status
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
            'ids'			=>	'required|array',
            'archived'		=>	'required|integer',
        ];
    }
}
