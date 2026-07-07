<?php

namespace App\Modules\Notifications;

use App\Jobs\SendEmailJob;
use App\Traits\CustomMailSettings;
use App\Mail\SystemAlert;
use App\Models\SystemNotification;
use App\Modules\InvoiceGeneration\InvoiceDBOperations;
use App\Modules\Notifications\Enums\NotificationType;
use Illuminate\Support\Facades\Log;

class Notification
{
    use CustomMailSettings;

	public function __construct(private InvoiceDBOperations $invoice_db_operations){}

    /**
     * Log a notification to DB and email the account owner.
     *
     * @param int $company_id
     * @param NotificationType $type
     * @param string $title
     * @param string $message
     * @param array $data
     */
    public function notify(int $company_id, NotificationType $type, string $title, string $message, array $data = []) : void {

        try {
            SystemNotification::create([
                'company_id' => $company_id,
                'type'       => $type->value,
                'title'      => $title,
                'message'    => $message,
                'data'       => json_encode($data),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to insert system notification: ' . $e->getMessage());
        }

       	$info = $this->invoice_db_operations->fetchAdminEmails();

		if($info){

			$first_email = $info[0]['email'];
			$first_name = $info[0]['name'];

			array_shift($info);

			$info = array_values($info);

			$pass_data = [
				'type'			=>	$type->value,
				'title'			=>	$title,
				'message'		=>	$message,
				'data'			=>	$data
			];

			SendEmailJob::dispatch(
				to: $first_email,
				to_name: $first_name,
				mailable_class: SystemAlert::class,
				mailable_data: [$pass_data],
				smtp: $this->smtpSettings(),
				cc: $info
			);
		}
    }
}