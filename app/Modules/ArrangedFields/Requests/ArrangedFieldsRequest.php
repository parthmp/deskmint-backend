<?php

namespace App\Modules\ArrangedFields\Requests;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class ArrangedFieldsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	protected function prepareForValidation(){

		$rows = [];

		$company_id = (int) Sanitize::input($this->input('company_id'));

		if($this->input('rows')){
			
			foreach($this->input('rows') as $row){

				$temp = [];
				$temp['id'] = (int) Sanitize::input($row['id']);
				$temp['text'] = Sanitize::input($row['text']);
				$temp['value'] = isset($row['value']) ? Sanitize::input($row['value']) : null;
				$temp['type'] = isset($row['type']) ? Sanitize::input($row['type']) : null;
				
				
				if(isset($row['tax'])){
					$temp['tax'] = (bool) Sanitize::input($row['tax']);
				}

				if(isset($row['tax_rate'])){
					$temp['tax_rate'] = (float) Sanitize::input($row['tax_rate']);
				}

				if(isset($row['mapped'])){
					if(is_array($row['mapped'])){
						$temp['mapped'] = Sanitize::recursive($row['mapped']);
					}else{
						$temp['mapped'] = Sanitize::input($row['mapped']);
					}
					
				}else{
					$temp['mapped'] = '';
				}

				if(isset($row['clients_custom_field_id'])){
					$temp['clients_custom_field_id'] = Sanitize::input($row['clients_custom_field_id']);
				}

				if(isset($row['invoices_custom_field_id'])){
					$temp['invoices_custom_field_id'] = Sanitize::input($row['invoices_custom_field_id']);
				}

				if(isset($row['id_column'])){
					$temp['id_column'] = (int) Sanitize::input($row['id_column']);
				}

				$rows[] = $temp;

			}

		}else{
			$rows = null;
		}

		

		$this->merge([
			'company_id'	=>		$company_id,
			'rows'			=>		$rows
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
            'company_id'        				=> 'required',
            'rows'              				=> 'required|array',
			'rows.*.id'         				=> 'required|integer',
			'rows.*.text'       				=> 'required|string',
			'rows.*.value'      				=> 'required|string',
			'rows.*.type'       				=> 'required|string|in:normal,custom',
			'rows.*.mapped'     				=> 'sometimes',
			'rows.*.tax'     					=> 'sometimes',
			'rows.*.tax_rate'   				=> 'sometimes',
			'rows.*.id_column'  				=> 'sometimes',
			'rows.*.clients_custom_field_id'  	=> 'sometimes',
			'rows.*.invoices_custom_field_id'  	=> 'sometimes'
        ];
    }
}
