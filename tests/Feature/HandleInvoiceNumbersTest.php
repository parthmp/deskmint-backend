<?php

namespace Tests\Feature;

use App\Services\HandleInvoiceNumbers;
use App\Traits\SettingsDefault;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class HandleInvoiceNumbersTest extends TestCase {
    
	use SettingsDefault, RefreshDatabase;

	public function test_default_invoice_number_behaviour_hin(){

		$handle_number = new HandleInvoiceNumbers(1, $this->getDefaultInvoiceNumbersSettings(), 330);
		$invoice_number = $handle_number->getNextInvoiceNumber();
		
		$this->assertEquals(1, (int) $invoice_number);
	}

	
	public function test_default_invoice_number_behaviour_with_padding_1_hin(){

		$settings = $this->getDefaultInvoiceNumbersSettings();
		$settings['number_padding'] = '01';
		$handle_number = new HandleInvoiceNumbers(1, $settings, 330);
		$invoice_number = $handle_number->getNextInvoiceNumber();
		
		$this->assertEquals('01', (string) $invoice_number);
	}

	public function test_default_invoice_number_behaviour_with_padding_2_hin(){

		$settings = $this->getDefaultInvoiceNumbersSettings();
		$settings['number_padding'] = '901';
		$handle_number = new HandleInvoiceNumbers(1, $settings, 330);
		$invoice_number = $handle_number->getNextInvoiceNumber();
		
		$this->assertEquals('901', (string) $invoice_number);
	}

	public function test_invoice_number_behaviour_with_pattern_1_hin(){

		$settings = $this->getDefaultInvoiceNumbersSettings();
		$settings['number_padding'] = '001';
		$settings['number_pattern'] = '{$year}';
		$handle_number = new HandleInvoiceNumbers(1, $settings, 330);
		$invoice_number = $handle_number->getNextInvoiceNumber();
		
		$this->assertEquals(date('Y').'001', (string) $invoice_number);
	}

	public function test_invoice_number_behaviour_with_pattern_2_hin(){

		$settings = $this->getDefaultInvoiceNumbersSettings();
		$settings['number_padding'] = '001';
		$settings['number_pattern'] = '_{$year}';
		$handle_number = new HandleInvoiceNumbers(1, $settings, 330);
		$invoice_number = $handle_number->getNextInvoiceNumber();
		
		$this->assertEquals('_'.date('Y').'001', (string) $invoice_number);
	}

	public function test_invoice_number_behaviour_with_pattern_3_hin(){

		$settings = $this->getDefaultInvoiceNumbersSettings();
		$settings['number_padding'] = '001';
		$settings['number_pattern'] = '_{$year}{$month_number}';
		$handle_number = new HandleInvoiceNumbers(1, $settings, 330);
		$invoice_number = $handle_number->getNextInvoiceNumber();
		
		$this->assertEquals('_'.date('Y').date('m').'001', (string) $invoice_number);
	}

	public function test_invoice_number_behaviour_with_pattern_4_hin(){

		$settings = $this->getDefaultInvoiceNumbersSettings();
		$settings['number_padding'] = '001';
		$settings['number_pattern'] = '{$day_number}_{$year}{$month_number}';
		$handle_number = new HandleInvoiceNumbers(1, $settings, 0);
		$invoice_number = $handle_number->getNextInvoiceNumber();
		
		$this->assertEquals(date('d').'_'.date('Y').date('m').'001', (string) $invoice_number);
	}

	public function test_invoice_number_behaviour_with_pattern_5_hin(){

		$settings = $this->getDefaultInvoiceNumbersSettings();
		$settings['number_padding'] = '001';
		$settings['number_pattern'] = '{$year}{$month_number}{$day_number}AbC{$day_number}{$month_full_name}{$month_short_name}{$month_number}{$day_name}{$day_number}{$year}';
		$handle_number = new HandleInvoiceNumbers(1, $settings, 0);
		$invoice_number = $handle_number->getNextInvoiceNumber();
		
		$this->assertEquals(date('Y').date('m').date('d').'AbC'.date('d').date('F').date('M').date('m').date('l').date('d').date('Y').'001', (string) $invoice_number);
	}
	//TODO : continue testing for reset conter for invoice number after invoice insert tests
	// public function test_invoice_number_behaviour_with_pattern_reset_counter_1_hin(){

	// 	$settings = $this->getDefaultInvoiceNumbersSettings();
	// 	$settings['number_padding'] = '001';
	// 	$settings['number_pattern'] = '{$year}_{$month_number}+{$day_number}';
	// 	$settings['reset_counter'] = 'daily';
	// 	$handle_number = new HandleInvoiceNumbers(1, $settings, 0);
	// 	$invoice_number = $handle_number->getNextInvoiceNumber();
		
	// 	$this->assertEquals(date('Y').'_'.date('m').'+'.date('d').'001', (string) $invoice_number);
	// }

}
