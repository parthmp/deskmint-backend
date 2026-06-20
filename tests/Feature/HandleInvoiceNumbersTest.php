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

}
