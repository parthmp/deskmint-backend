<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Modules\InvoiceGeneration\InvoiceEmailContent;
use App\Traits\CustomMailSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;

class SendPaymentReminderJob implements ShouldQueue
{
    use Queueable, CustomMailSettings;

    /**
     * Create a new job instance.
     */
    public function __construct(
		private int $invoice_id,
		private string $email_content,
		private string $client_email,
		private string $client_first_name,
		private string $client_last_name,
		private string $currency_code){}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
		$invoice = Invoice::query()
			->select([
				'id',
				'uuid',
				'client_id',
				'currency_id',
				'company_id',
				'invoice_number',
				'invoice_date',
				'due_date',
				'total',
				'balance_due',
				'status',
				'pdf_file',
				'xml_file',
				'payment_gateway',
				'timezone_offset_minutes',
			])
			->find($this->invoice_id);

		if($invoice){
			
			$email_content_maker = new InvoiceEmailContent();
			$parsed = $email_content_maker->setDisk(INVOICES_DISK)
										->setInvoice($invoice)
										->setInvoiceContent($this->email_content)
										->setAltCurrencyCode($this->currency_code)
										->setAltClientFirstName($this->client_first_name)
										->setAltClientLastName($this->client_last_name)
										->getContent();

			$parsed['subject'] = 'Payment Reminder for Invoice #'.$invoice->invoice_number;
			Bus::chain([
				new SendEmailJob(
					to: $this->client_email,
					to_name: $this->client_first_name.' '.$this->client_last_name,
					mailable_class: \App\Mail\SendInvoice::class,
					mailable_data: [$parsed],
					smtp: $this->smtpSettings()
				),
				new MarkInvoiceReminderSentJob($this->invoice_id)
			])->dispatch();
			
		}

    }
}
