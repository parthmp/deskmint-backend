<?php

	namespace App\Traits;

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

	}