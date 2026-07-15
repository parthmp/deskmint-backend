<?php

namespace Tests\Feature;

use App\Jobs\SendPaymentReminderJob;
use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\SettingsSection;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Traits\SettingsDefault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PaymentReminderCommandTest extends TestCase
{
	use RefreshDatabase, SettingsDefault;
	
    public function test_it_skips_invoice_when_client_has_reminders_disabled_1_prct(){
		
        Bus::fake();
		
        $company = Company::factory()->create();
       
        $client = Client::factory()->create([
            'company_id' 		=> $company->id,
            'currency_id' 		=> 5,
            'send_reminders' 	=> 0,
        ]);

        Invoice::factory()->create([
            'company_id' 			=> $company->id,
            'client_id' 			=> $client->id,
            'currency_id'			=> 5,
            'status' 				=> InvoiceStatus::PENDING->value,
            'reminders_sent' 		=> 0,
            'last_reminder_sent_at' => now()->subDays(10),
        ]);

        $this->artisan('deskmint:payment-reminder');

        Bus::assertNotDispatched(SendPaymentReminderJob::class);
    }

	public function test_it_skips_invoice_when_time_is_not_up_2_prct(){
		
        Bus::fake();
		
        $company = Company::factory()->create();
       
        $client = Client::factory()->create([
            'company_id' 		=> $company->id,
            'currency_id' 		=> 5,
            'send_reminders' 	=> 1,
        ]);

        Invoice::factory()->create([
            'company_id' 			=> $company->id,
            'client_id' 			=> $client->id,
            'currency_id'			=> 5,
            'status' 				=> InvoiceStatus::PENDING->value,
            'reminders_sent' 		=> 0,
            'last_reminder_sent_at' => now()->subDays(1),
        ]);

        $this->artisan('deskmint:payment-reminder');

        Bus::assertNotDispatched(SendPaymentReminderJob::class);
    }

	public function test_it_skips_invoice_when_time_is_not_up_3_prct(){
		
        Bus::fake();
		
        $company = Company::factory()->create();
       
        $client = Client::factory()->create([
            'company_id' 		=> $company->id,
            'currency_id' 		=> 5,
            'send_reminders' 	=> 1,
        ]);

		$settings = new SettingsSection();
		$settings->company_id = $company->id;
		$settings->type = ESC_EMAIL_REMINDERS_TYPE;
		$settings->settings_json = json_encode(['days_gap' => 2, 'send_n_times' => 5]);
		$settings->save();

        Invoice::factory()->create([
            'company_id' 			=> $company->id,
            'client_id' 			=> $client->id,
            'currency_id'			=> 5,
            'status' 				=> InvoiceStatus::PENDING->value,
            'reminders_sent' 		=> 0,
            'last_reminder_sent_at' => now()->subDays(1),
        ]);

        $this->artisan('deskmint:payment-reminder');

        Bus::assertNotDispatched(SendPaymentReminderJob::class);
    }

	public function test_it_skips_invoice_when_limit_reached_4_prct(){
		
        Bus::fake();
		
        $company = Company::factory()->create();
       
        $client = Client::factory()->create([
            'company_id' 		=> $company->id,
            'currency_id' 		=> 5,
            'send_reminders' 	=> 1,
        ]);

		$settings = new SettingsSection();
		$settings->company_id = $company->id;
		$settings->type = ESC_EMAIL_REMINDERS_TYPE;
		$settings->settings_json = json_encode(['days_gap' => 2, 'send_n_times' => 5]);
		$settings->save();

        Invoice::factory()->create([
            'company_id' 			=> $company->id,
            'client_id' 			=> $client->id,
            'currency_id'			=> 5,
            'status' 				=> InvoiceStatus::PENDING->value,
            'reminders_sent' 		=> 5,
            'last_reminder_sent_at' => now()->subDays(1),
        ]);

        $this->artisan('deskmint:payment-reminder');

        Bus::assertNotDispatched(SendPaymentReminderJob::class);
    }

	public function test_it_skips_invoice_when_limit_reached_5_prct(){
		
        Bus::fake();
		
        $company = Company::factory()->create();
       
        $client = Client::factory()->create([
            'company_id' 		=> $company->id,
            'currency_id' 		=> 5,
            'send_reminders' 	=> 1,
        ]);

		$default = $this->getDefaultEmailRemindersSettings();
		
        Invoice::factory()->create([
            'company_id' 			=> $company->id,
            'client_id' 			=> $client->id,
            'currency_id'			=> 5,
            'status' 				=> InvoiceStatus::PENDING->value,
            'reminders_sent' 		=> $default['send_n_times'],
            'last_reminder_sent_at' => now()->subDays(1),
        ]);

        $this->artisan('deskmint:payment-reminder');

        Bus::assertNotDispatched(SendPaymentReminderJob::class);
    }

	public function test_it_sends_invoice_when_limit_not_reached_6_prct(){
		
        Bus::fake();
		
        $company = Company::factory()->create();
       
        $client = Client::factory()->create([
            'company_id' 		=> $company->id,
            'currency_id' 		=> 5,
            'send_reminders' 	=> 1,
        ]);

		$default = $this->getDefaultEmailRemindersSettings();
		
        Invoice::factory()->create([
            'company_id' 			=> $company->id,
            'client_id' 			=> $client->id,
            'currency_id'			=> 5,
            'status' 				=> InvoiceStatus::PENDING->value,
            'reminders_sent' 		=> ($default['send_n_times']-1),
            'last_reminder_sent_at' => now()->subDays($default['days_gap']+1),
        ]);

        $this->artisan('deskmint:payment-reminder');

        Bus::assertDispatched(SendPaymentReminderJob::class);
    }

	public function test_it_skips_invoice_for_non_pending_invoices_6_prct(){
		
        Bus::fake();
		
        $company = Company::factory()->create();
       
        $client = Client::factory()->create([
            'company_id' 		=> $company->id,
            'currency_id' 		=> 5,
            'send_reminders' 	=> 1,
        ]);

		$default = $this->getDefaultEmailRemindersSettings();
		
        Invoice::factory()->create([
            'company_id' 			=> $company->id,
            'client_id' 			=> $client->id,
            'currency_id'			=> 5,
            'status' 				=> InvoiceStatus::CANCELLED->value,
            'reminders_sent' 		=> ($default['send_n_times']-1),
            'last_reminder_sent_at' => now()->subDays($default['days_gap']+1),
        ]);

        $this->artisan('deskmint:payment-reminder');

        Bus::assertNotDispatched(SendPaymentReminderJob::class);
    }

	public function test_it_skips_invoice_for_non_pending_invoices_7_prct(){
		
        Bus::fake();
		
        $company = Company::factory()->create();
       
        $client = Client::factory()->create([
            'company_id' 		=> $company->id,
            'currency_id' 		=> 5,
            'send_reminders' 	=> 1,
        ]);

		$default = $this->getDefaultEmailRemindersSettings();
		
        Invoice::factory()->create([
            'company_id' 			=> $company->id,
            'client_id' 			=> $client->id,
            'currency_id'			=> 5,
            'status' 				=> InvoiceStatus::PAID->value,
            'reminders_sent' 		=> ($default['send_n_times']-1),
            'last_reminder_sent_at' => now()->subDays($default['days_gap']+1),
        ]);

        $this->artisan('deskmint:payment-reminder');

        Bus::assertNotDispatched(SendPaymentReminderJob::class);
    }

	public function test_it_sends_invoice_for_partially_paid_invoices_8_prct(){
		
        Bus::fake();
		
        $company = Company::factory()->create();
       
        $client = Client::factory()->create([
            'company_id' 		=> $company->id,
            'currency_id' 		=> 5,
            'send_reminders' 	=> 1,
        ]);

		$default = $this->getDefaultEmailRemindersSettings();
		
        $invoice = Invoice::factory()->create([
            'company_id' 			=> $company->id,
            'client_id' 			=> $client->id,
            'currency_id'			=> 5,
            'status' 				=> InvoiceStatus::PARTIALLY_PAID->value,
            'reminders_sent' 		=> ($default['send_n_times']-1),
            'last_reminder_sent_at' => now()->subDays($default['days_gap']+1),
        ]);

        $this->artisan('deskmint:payment-reminder');

        Bus::assertDispatched(SendPaymentReminderJob::class);
    }

	public function test_it_dispatches_correct_number_of_jobs_across_multiple_companies_9() {
        Bus::fake();

        $company_a = Company::factory()->create();
        $company_b = Company::factory()->create();
        
        $client_a = Client::factory()->create(['company_id' => $company_a->id, 'send_reminders' => 1]);
        $client_b = Client::factory()->create(['company_id' => $company_b->id, 'send_reminders' => 1]);

        $invoice_a = Invoice::factory()->create([
            'company_id' => $company_a->id,
            'client_id' => $client_a->id,
            'currency_id' => 5,
            'status' => InvoiceStatus::PENDING->value,
            'reminders_sent' => 0,
            'last_reminder_sent_at' => now()->subDays(10),
        ]);

        $invoice_b = Invoice::factory()->create([
            'company_id' => $company_b->id,
            'client_id' => $client_b->id,
            'currency_id' => 5,
            'status' => InvoiceStatus::PENDING->value,
            'reminders_sent' => 0,
            'last_reminder_sent_at' => now()->subDays(10),
        ]);

        $this->artisan('deskmint:payment-reminder');

        Bus::assertDispatched(SendPaymentReminderJob::class, 2);
    }

}
