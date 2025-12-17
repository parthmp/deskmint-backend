<?php

	namespace App\Traits;

	use App\Helpers\General;
	use App\Models\AdditionalCompanyField;
	use App\Models\AdditionalProductColumnsField;
	use App\Models\ClientsCustomField;
	use App\Models\InvoicesCustomField;
	use Illuminate\Support\Collection;

	trait SettingsDefault{

		public function getDefaultInvoiceGeneralSettings() : array{

			return [
				'template'				=>	'plain',
				'font_size'				=>	16,
				'logo_size'				=>	100,
				'primary_color'			=>	'#055f40',
				'secondary_color'		=>	'#118b65',
				'e_invoice_on'			=>	false
			];

		}

		public function getDefaultInvoiceNumbersSettings() : array{

			return [
				'number_padding' 	=> '1',
				'reset_counter' 	=> 'never',
				'number_pattern'	=>	''
			];

		}

		public function getCustomFields(string $model, int $index = 1, $column = 'clients_custom_field_id') : array{
			
			$formatted = [];

			$fields =  $model::with(['customFieldType', 'customFieldValue'])->whereHas('customFieldType')->get();

			foreach($fields as $field){

				$formatted[] = [
					'id'						=>	$index,
					'text'						=>	$field->label,
					'value'						=>	General::replaceWithUnderscores($field->label),
					'mapped'					=>	'',
					'type'						=>	'custom',
					$column						=>	$field->id
				];

				$index++;

			}

			return $formatted;

		}

		public function getDefaultInvoiceClientDetailsSettings() : array{

			$custom_fields = $this->getCustomFields(ClientsCustomField::class, 4);

			$data = [
				'rows' => [
					[
						'id'		=>	1,
						'text'		=>	'Name',
						'value'		=>	General::replaceWithUnderscores('Name'),
						'mapped'	=>	['first_name', 'last_name'], /* from db */
						'type'		=>	'normal'
					],
					[
						'id'		=>	2,
						'text'		=>	'GST/TAX #',
						'value'		=>	General::replaceWithUnderscores('GST/TAX #'),
						'mapped'	=>	['tax_number'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	3,
						'text'		=>	'Street',
						'value'		=>	General::replaceWithUnderscores('Street'),
						'mapped'	=>	['billing_street'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	4,
						'text'		=>	'Apt/Suite',
						'value'		=>	General::replaceWithUnderscores('Apt/Suite'),
						'mapped'	=>	['billing_apt'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	5,
						'text'		=>	'City - State - Postal',
						'value'		=>	General::replaceWithUnderscores('City - State - Postal'),
						'mapped'	=>	['billing_city', 'billing_state', 'billing_postal_code'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	6,
						'text'		=>	'Country',
						'value'		=>	General::replaceWithUnderscores('Country'),
						'mapped'	=>	['billing_country_id'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	7,
						'text'		=>	'Phone',
						'value'		=>	General::replaceWithUnderscores('Phone'),
						'mapped'	=>	['phone'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	8,
						'text'		=>	'Email',
						'value'		=>	General::replaceWithUnderscores('Email'),
						'mapped'	=>	['email'],
						'type'		=>	'normal'
					]
				],
				'dropdown' => [
					[
						'id'		=>	1,
						'text'		=>	'Postal City',
						'value'		=>	General::replaceWithUnderscores('Postal City'),
						'mapped'	=>	['shipping_city'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	2,
						'text'		=>	'Postal City - State',
						'value'		=>	General::replaceWithUnderscores('Postal City - State'),
						'mapped'	=>	['shipping_city', 'shipping_state'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	3,
						'text'		=>	'Website',
						'value'		=>	General::replaceWithUnderscores('Website'),
						'mapped'	=>	['website'],
						'type'		=>	'normal'
					]
				]
			];

			foreach($custom_fields as $field){
				array_push($data['dropdown'], $field);
			}

			return $data;

		}

		public function getCompanyAdditionalFields(int $company_id, int $index = 1) : array{

			$structure = [];

			$fields = AdditionalCompanyField::select('id', 'label', 'value')->where('company_id', '=', $company_id)->get();

			foreach($fields as $field){

				$temp = [];

				$temp['id'] = $index;
				$temp['text'] = $field->label;
				$temp['value'] = $field->label;
				$temp['mapped'] = '';
				$temp['type'] = 'custom';
				$temp['id_column'] = $field->id; /* this maps to the "id" column in additional_company_fields table */

				$structure[] = $temp;

				$index++;

			}

			return $structure;
		}

		public function getDefaultInvoiceCompanyDetailsSettings(int $company_id) : array{

			$additional_fields = $this->getCompanyAdditionalFields($company_id, 4);
			/* mapped to columns from companies table */
			$data = [
				'rows' => [
					[
						'id'		=>	1,
						'text'		=>	'Company name',
						'value'		=>	General::replaceWithUnderscores('company name'),
						'mapped'	=>	['company_name'], /* from db */
						'type'		=>	'normal'
					],
					[
						'id'		=>	2,
						'text'		=>	'Company id',
						'value'		=>	General::replaceWithUnderscores('Company id'),
						'mapped'	=>	['id_number'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	3,
						'text'		=>	'GST - VAT number',
						'value'		=>	General::replaceWithUnderscores('GST - VAT number'),
						'mapped'	=>	['gst_vat_number'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	4,
						'text'		=>	'Website',
						'value'		=>	General::replaceWithUnderscores('Website'),
						'mapped'	=>	['website'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	5,
						'text'		=>	'Email',
						'value'		=>	General::replaceWithUnderscores('email'),
						'mapped'	=>	['email'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	6,
						'text'		=>	'Phone',
						'value'		=>	General::replaceWithUnderscores('phone'),
						'mapped'	=>	['phone'],
						'type'		=>	'normal'
					]
				],
				'dropdown' => [
					[
						'id'		=>	1,
						'text'		=>	'Apt - Suite',
						'value'		=>	General::replaceWithUnderscores('Apt - Suite'),
						'mapped'	=>	['apt'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	2,
						'text'		=>	'City/State/Postal',
						'value'		=>	General::replaceWithUnderscores('City/State/Postal'),
						'mapped'	=>	['city', 'state', 'postal_code'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	3,
						'text'		=>	'Street',
						'value'		=>	General::replaceWithUnderscores('Street'),
						'mapped'	=>	['address_street'],
						'type'		=>	'normal'
					]
				]
			];

			foreach($additional_fields as $field){
				array_push($data['dropdown'], $field);
			}

			return $data;

		}

		public function getDefaultInvoiceCompanyAddressSettings(int $company_id){

			$additional_fields = $this->getCompanyAdditionalFields($company_id, 4);
			/* mapped to columns from companies table */
			$data = [
				'rows' => [
					[
						'id'		=>	1,
						'text'		=>	'Street',
						'value'		=>	General::replaceWithUnderscores('Street'),
						'mapped'	=>	['address_street'], /* from db */
						'type'		=>	'normal'
					],
					[
						'id'		=>	2,
						'text'		=>	'Apt - Suite',
						'value'		=>	General::replaceWithUnderscores('Apt - Suite'),
						'mapped'	=>	['apt'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	3,
						'text'		=>	'City/State/Postal',
						'value'		=>	General::replaceWithUnderscores('City/State/Postal'),
						'mapped'	=>	['city', 'state', 'postal_code'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	4,
						'text'		=>	'Country',
						'value'		=>	General::replaceWithUnderscores('Country'),
						'mapped'	=>	['country_id'],
						'type'		=>	'normal'
					]
				],
				'dropdown' => [
					[
						'id'		=>	1,
						'text'		=>	'Email',
						'value'		=>	General::replaceWithUnderscores('Email'),
						'mapped'	=>	['email'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	2,
						'text'		=>	'ID number',
						'value'		=>	General::replaceWithUnderscores('ID number'),
						'mapped'	=>	['id_number'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	3,
						'text'		=>	'Name',
						'value'		=>	General::replaceWithUnderscores('Name'),
						'mapped'	=>	['company_name'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	4,
						'text'		=>	'Phone',
						'value'		=>	General::replaceWithUnderscores('Phone'),
						'mapped'	=>	['phone'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	5,
						'text'		=>	'Postal/City/State',
						'value'		=>	General::replaceWithUnderscores('Postal/City/State'),
						'mapped'	=>	['postal_code', 'city', 'state'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	6,
						'text'		=>	'Postal/City',
						'value'		=>	General::replaceWithUnderscores('Postal/City'),
						'mapped'	=>	['postal_code', 'city'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	7,
						'text'		=>	'GST - VAT number',
						'value'		=>	General::replaceWithUnderscores('GST - VAT number'),
						'mapped'	=>	['gst_vat_number'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	8,
						'text'		=>	'Website',
						'value'		=>	General::replaceWithUnderscores('Website'),
						'mapped'	=>	['website'],
						'type'		=>	'normal'
					]
				]
			];

			foreach($additional_fields as $field){
				array_push($data['dropdown'], $field);
			}

			return $data;

		}

		public function getDefaultInvoiceDetailsSettings(int $company_id) : array{

			$custom_fields = $this->getCustomFields(InvoicesCustomField::class, 3, 'invoices_custom_field_id');

			$data = [
				'rows' => [
					[
						'id'		=>	1,
						'text'		=>	'Number',
						'value'		=>	General::replaceWithUnderscores('Number'),
						'mapped'	=>	['invoice_number'], /* from db */
						'type'		=>	'normal'
					],
					[
						'id'		=>	2,
						'text'		=>	'date',
						'value'		=>	General::replaceWithUnderscores('date'),
						'mapped'	=>	['invoice_date'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	3,
						'text'		=>	'Due Date',
						'value'		=>	General::replaceWithUnderscores('due_date'),
						'mapped'	=>	['due_date'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	4,
						'text'		=>	'Total',
						'value'		=>	General::replaceWithUnderscores('Total'),
						'mapped'	=>	['total'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	5,
						'text'		=>	'Balance Due',
						'value'		=>	General::replaceWithUnderscores('Balance Due'),
						'mapped'	=>	['balance_due'],
						'type'		=>	'normal'
					]
				],
				'dropdown' => [
					[
						'id'		=>	1,
						'text'		=>	'Amount',
						'value'		=>	General::replaceWithUnderscores('Amount'),
						'mapped'	=>	['amount'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	2,
						'text'		=>	'PO Number',
						'value'		=>	General::replaceWithUnderscores('PO Number'),
						'mapped'	=>	['po_number'],
						'type'		=>	'normal'
					]
				]
			];

			foreach($custom_fields as $field){
				array_push($data['dropdown'], $field);
			}

			return $data;
			
		}

		
		public function getProductColumnsAdditionalFields(int $company_id, int $index = 1) : array{

			$structure = [];

			$fields = AdditionalProductColumnsField::select('id', 'label', 'type', 'tax_rate')->where('company_id', '=', $company_id)->get();

			foreach($fields as $field){

				$label = $field->label;

				// if($field->type === 'tax'){
				// 	$label .= ' - '.$field->tax_rate.'%';
				// }
				
				$tax = false;
				if($field->type === 'tax'){
					$tax = true;
				}
				

				$temp = [];

				$temp['id'] = $index;
				$temp['text'] = $label;
				$temp['value'] = $label;
				$temp['mapped'] = '';
				$temp['tax'] = $tax;
				$temp['tax_rate'] = $field->tax_rate;
				$temp['type'] = 'custom';
				$temp['id_column'] = $field->id; /* this maps to the "id" column in additional_product_columns_fields table */

				$structure[] = $temp;

				$index++;

			}

			return $structure;
		}

		public function getDefaultProductColumnsSettings(int $company_id){
			
			$additional_fields = $this->getProductColumnsAdditionalFields($company_id, 2);

			/* normal fields maps to invoice_items db fields */
			/* additional fields maps to additional_product_columns_fields db fields */
			$data = [
				'rows' => [
					[
						'id'		=>	1,
						'text'		=>	'Item',
						'value'		=>	General::replaceWithUnderscores('Item'),
						'mapped'	=>	['product_id'], /* from db */
						'tax'		=>	false,
						'type'		=>	'normal'
					],
					[
						'id'		=>	2,
						'text'		=>	'Description',
						'value'		=>	General::replaceWithUnderscores('Description'),
						'mapped'	=>	['description'],
						'tax'		=>	false,
						'type'		=>	'normal'
					],
					[
						'id'		=>	3,
						'text'		=>	'Unit cost',
						'value'		=>	General::replaceWithUnderscores('Unit cost'),
						'mapped'	=>	['unit_price'],
						'tax'		=>	false,
						'type'		=>	'normal'
					],
					[
						'id'		=>	4,
						'text'		=>	'Quantity',
						'value'		=>	General::replaceWithUnderscores('Quantity'),
						'mapped'	=>	['quantity'],
						'tax'		=>	false,
						'type'		=>	'normal'
					],
					// [
					// 	'id'		=>	5,
					// 	'text'		=>	'Discount',
					// 	'value'		=>	General::replaceWithUnderscores('discount'),
					// 	'mapped'	=>	['discount'],
					// 	'type'		=>	'normal'
					// ],
					[
						'id'		=>	6,
						'text'		=>	'Tax',
						'value'		=>	General::replaceWithUnderscores('Tax'),
						'mapped'	=>	['tax'],
						'tax'		=>	true,
						'tax_rate'	=>	0,
						'type'		=>	'normal'
					],
					[
						'id'		=>	7,
						'text'		=>	'Line total',
						'value'		=>	General::replaceWithUnderscores('Line total'),
						'mapped'	=>	['line_total'],
						'tax'		=>	false,
						'type'		=>	'normal'
					]
				],
				'dropdown' => [
					// [
					// 	'id'		=>	1,
					// 	'text'		=>	'Gross line total',
					// 	'value'		=>	General::replaceWithUnderscores('gross_line_total'),
					// 	'mapped'	=>	['gross_line_total'],
					// 	'type'		=>	'normal'
					// ]
				]
			];

			foreach($additional_fields as $field){
				array_push($data['dropdown'], $field);
			}

			return $data;

		}

		public function getDefaultTotalFieldsSettings() : array {

			/* none of these fields are mapped with db table columns, all fields will be generated on the fly while generating invoices */

			$data = [
				'rows' => [
					// [
					// 	'id'		=>	1,
					// 	'text'		=>	'Net Subtotal',
					// 	'value'		=>	General::replaceWithUnderscores('Net Subtotal'),
					// 	'mapped'	=>	['net_subtotal'], /* these are not db columns like others, these added as indicators to differentiate */
					// 	'type'		=>	'normal'
					// ],
					[
						'id'		=>	2,
						'text'		=>	'Subtotal',
						'value'		=>	General::replaceWithUnderscores('Subtotal'),
						'mapped'	=>	['sub_total'],  /* these are not db columns like others, these added as indicators to differentiate */
						'type'		=>	'normal'
					],
					[
						'id'		=>	3,
						'text'		=>	'Discount',
						'value'		=>	General::replaceWithUnderscores('Discount'),
						'mapped'	=>	['discount'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	4,
						'text'		=>	'Total Taxes',
						'value'		=>	General::replaceWithUnderscores('Total Taxes'),
						'mapped'	=>	['total_taxes'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	5,
						'text'		=>	'Total',
						'value'		=>	General::replaceWithUnderscores('Total'),
						'mapped'	=>	['total'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	6,
						'text'		=>	'Paid to Date',
						'value'		=>	General::replaceWithUnderscores('Paid to Date'),
						'mapped'	=>	['paid_to_date'],
						'type'		=>	'normal'
					],
					[
						'id'		=>	7,
						'text'		=>	'Balance Due',
						'value'		=>	General::replaceWithUnderscores('Balance Due'),
						'mapped'	=>	['balance_due'],
						'type'		=>	'normal'
					],
				],
				'dropdown' => [
					
				]
			];

			return $data;

		}

		/**
		 * getInvoiceResetSettings function
		 *
		 * @return array
		 */
		public function getInvoiceResetSettings() : array {

			return [
				'reset' 	=> '0'
			];

		}

		/**
		 * getDefaultEmailContentSettings function
		 *
		 * @return array
		 */
		public function getDefaultEmailContentSettings() : array {

			return [
				'email_content_invoice'		=>	'',
				'email_content_reminder'	=>	'',
				'payment_details'			=>	'',
			];

		}

		/**
		 * getDefaultEmailRemindersSettings function
		 *
		 * @return array
		 */
		public function getDefaultEmailRemindersSettings() : array {

			return [
				'send_n_times'	=>	'3',
				'days_gap'		=>	'2',
			];

		}

		/**
		 * getDefaultEmailSMTPSettings function
		 *
		 * @return array
		 */
		public function getDefaultEmailSMTPSettings() : array {

			$encryption = env('MAIL_ENCRYPTION') ?? 'tls';

			$encryption = strtolower($encryption);

			return [
				'host'					=>	env('MAIL_HOST'),
				'port'					=>	env('MAIL_PORT'),
				'username'				=>	env('MAIL_USERNAME'),
				'password'				=>	env('MAIL_PASSWORD'),
				'mail_from_address'		=>	env('MAIL_FROM_ADDRESS'),
				'mail_from_name'		=>	env('MAIL_FROM_NAME'),
				'reply_to_address'		=>	env('MAIL_REPLYTO_ADDRESS'),
				'encryption'			=>	$encryption,
				'test_email_address'	=>	'',
			];

		}

		/**
		 * getDefaultPayPalSettings function
		 *
		 * @return array
		 */
		public function getDefaultPayPalSettings() : array {

			return [
				'client_id' => 	'',
				'secret'	=>	'',
				'mode'		=>	''
			];

		}

		/**
		 * getDefaultStripeSettings function
		 *
		 * @return array
		 */
		public function getDefaultStripeSettings() : array {

			return [
				'secret'	=>	''
			];

		}

	}