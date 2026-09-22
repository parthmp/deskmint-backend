<?php

namespace App\Traits;

use App\Helpers\General;
use App\Models\AdditionalProductColumnsField;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Repositories\Client\ClientRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Invoice\Exceptions\InvoiceException;
use App\Services\Invoice\InvoiceSettingsService;
use Illuminate\Http\Request;

trait InvoiceValidation {
	
	/**
	 * getProductRepository function
	 *
	 * @return ProductRepository
	 */
	private function getProductRepository() : ProductRepository {
        return app(ProductRepository::class);
    }

	/**
	 * getInvoiceSettingsService function
	 *
	 * @return InvoiceSettingsService
	 */
	private function getInvoiceSettingsService() : InvoiceSettingsService {
        return app(InvoiceSettingsService::class);
    }

	/**
	 * getClientRepository function
	 *
	 * @return ClientRepository
	 */
	private function getClientRepository() : ClientRepository {
        return app(ClientRepository::class);
    }

	/**
	 * shouldHaveAtLeastOneRow function
	 *
	 * @param array $product_rows
	 * @param integer $company_id
	 * @return boolean
	 */
	private function shouldHaveAtLeastOneRow(array $product_rows, int $company_id, string $ex = InvoiceException::class) : bool {

		if(empty($product_rows)){
			return false;
		}

		// Extract all product IDs
		$product_ids = [];
		foreach($product_rows as $row){
			
			if(!isset($row['row_uuid'])){
				throw new $ex('Please have at least one product to create invoice', 'invalid_product_uuid_tab0', config('global.error_code'), 0);
			}

			if(trim($row['row_uuid']) === ''){
				throw new $ex('Please have at least one product to create invoice', 'invalid_product_uuid_tab0', config('global.error_code'), 0);
			}

			if(!empty($row['product_id'])){
				$product_ids[] = (int) $row['product_id'];
			}
		}

		if(empty($product_ids)){
			return false;
		}

		// Check if at least one exists in database for this company
		return $this->getProductRepository()->ifProductsExists($company_id, $product_ids);

	}

	/**
	 * Undocumented function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	protected function getCustomTaxIds(int $company_id) : array{
		return AdditionalProductColumnsField::where([['company_id', '=', $company_id], ['type', '=', 'tax']])->pluck('id')->toArray();
	}

	/**
	 * isCustomColumn function
	 *
	 * @param array $column
	 * @return boolean
	 */
	protected function isCustomColumn(array $column): bool {
        return ($column['mapped'] === null || $column['mapped'] === '') && $column['type'] === 'custom';
    }

	/**
	 * generateFieldName function
	 *
	 * @param array $column
	 * @param array $custom_tax_ids
	 * @return string
	 */
	protected function generateFieldName(array $column, array $custom_tax_ids): string {

        $underscored = General::replaceWithUnderscores($column['text']);
        return in_array($column['id_column'], $custom_tax_ids) ? 'custom_tax_' . $underscored : 'normal_' . $underscored;

    }

	/**
	 * ifSubmittedFieldsAreSameAsDefined function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return boolean
	 */
	public function ifSubmittedFieldsAreSameAsDefined(Request $request, int $company_id) : bool {

		$invoice_settings = $this->getInvoiceSettingsService()->setCompany($company_id);

		$product_columns = $invoice_settings->getProductColumns();

		$product_rows = $request->input('data.product_rows');

		/* now check if all fields exist */

		$fields_same = true;

		$product_row_fields_names = [];
		
		foreach($product_rows[0] as $key => $submitted_col){
			$product_row_fields_names[] = $key;
		}

		$custom_tax_ids = $this->getCustomTaxIds($company_id);
		
		foreach($product_columns as $user_defined_column){
			
			/* this "normal is for normal fields from DB, not for taxes" */
			if($user_defined_column['mapped'] !== null && $user_defined_column['type'] === 'normal' && !in_array($user_defined_column['mapped'][0], $product_row_fields_names)){
				$fields_same = false;
				break;
			}

			
			if(($user_defined_column['mapped'] === null || $user_defined_column['mapped'] === '') && ($user_defined_column['type'] === 'custom')){
				
				if(!isset($user_defined_column['id_column'])){
					$fields_same = false;
					break;
				}
				
				$custom_field_name = $this->generateFieldName($user_defined_column, $custom_tax_ids);
				
				if(!in_array($custom_field_name, $product_row_fields_names)){
					$fields_same = false;
					break;
				}

			}

			
		}

		return $fields_same;

	}

	
	/**
	 * validatePaymentGatewayCurrency function
	 *
	 * @param integer $client_id
	 * @param integer $payment_gateway
	 * @return array
	 */
	private function validatePaymentGatewayCurrency(int $client_id, int $payment_gateway) : array {
		
		$currency = $this->getClientRepository()->fetchClientCurrencyById($client_id);
		
		$currency_code = strtoupper(trim($currency->code));

		$is_valid = true;

		if($payment_gateway === PaymentGateway::PAYPAL->value){
			$is_valid = in_array($currency_code, config('payment.supported_currencies.paypal'));
		}else if($payment_gateway === PaymentGateway::STRIPE->value){
			$is_valid = in_array($currency_code, config('payment.supported_currencies.stripe'));
		}

		return ['code' => $currency_code, 'valid' => $is_valid];

	}
}