<?php

namespace App\Http\Requests\Product;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	protected function prepareForValidation(){

		$company_id = (int) Sanitize::input($this->input('company_id'));
		$product_name = Sanitize::input($this->input('product_name'));
		$price = $this->input('price') ? Sanitize::input($this->input('price')) : null;
		$sku = $this->input('sku') ? Sanitize::input($this->input('sku')) : null;
		$description = $this->input('description') ? Sanitize::input($this->input('description')) : null;

		$this->merge([
			'company_id'	=>		$company_id,
			'product_name'	=>		$product_name,
			'price'			=>		$price,
        	'sku'			=>		$sku,
        	'description'	=>		$description
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
        	'product_name'	=>	'required',
        	'price'			=>	'sometimes',
        	'sku'			=>	'sometimes',
        	'description'	=>	'sometimes'
        ];
    }
}
