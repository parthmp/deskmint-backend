<?php

namespace App\Http\Requests\InvoiceSettingsAPF;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateInvoiceSettingsAPFRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	/**
	 * sanitizeArrayValues function
	 *
	 * @param array $arr
	 * @param boolean $has_id
	 * @return array
	 */
	private function sanitizeArrayValues(array $arr, bool $has_id = false) : array {

		$temp_array = [];

		foreach($arr as $temp){
			
			$value = Sanitize::input($temp['value']);

			if($has_id && isset($temp['id'])){
				$id = Sanitize::input($temp['id']);
			}else{
				$id = null;
			}

			
			$temp_array[] = [
				'id'	=>	$id,
				'value'	=>	$value
			];
			
		}

		return $temp_array;

	}

	/**
	 * prepareForValidation function
	 *
	 * @return void
	 */
	protected function prepareForValidation(){
		
		$company_id = (int) Sanitize::input($this->input('company_id'));

		$labels = [];
		$types = [];
		$taxes = [];
		
		$input_labels = $this->input('labels');
		$input_types = $this->input('types');
		$input_taxes = $this->input('taxes');

		if(!is_array($input_labels)){
			$input_labels = [];
		}

		if(!is_array($input_types)){
			$input_types = [];
		}
		
		if(!is_array($input_taxes)){
			$input_taxes = [];
		}

		$labels = $this->sanitizeArrayValues($input_labels, true);
		$types = $this->sanitizeArrayValues($input_types, true);
		$taxes = $this->sanitizeArrayValues($input_taxes, true);

		$this->merge([
			'company_id'=>	$company_id,
			'labels'	=>	$labels,
			'types'		=>	$types,
			'taxes'		=>	$taxes
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
            'company_id'		=>	'required',
            'labels'			=>	'required|array',
			'types'				=>	'required|array',
			'taxes'				=>	'required|array',
			'labels.*.id'		=>	'sometimes',
			'labels.*.value'	=>	'required',
			'types.*.id'		=>	'sometimes',
			'types.*.value'		=>	'required',
			'taxes.*.id'		=>	'sometimes',
			'taxes.*.value'		=>	'required'
        ];
    }
}
