<?php

	namespace App\Traits;

use App\Helpers\General;
use App\Models\ClientsCustomField;

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

		public function getCustomFields(string $model, int $index = 1) : array{
			
			$formatted = [];

			$fields =  $model::with(['customFieldType', 'customFieldValue'])->whereHas('customFieldType')->whereHas('customFieldValue')->get();

			foreach($fields as $field){

				$formatted[] = [
					'id'						=>	$index,
					'text'						=>	$field->label,
					'value'						=>	General::replaceWithUnderscores($field->label),
					'mapped'					=>	'',
					'type'						=>	'custom',
					'clients_custom_fields_id'	=>	$field->id
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

	}