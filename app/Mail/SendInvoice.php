<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class SendInvoice extends Mailable {
	
    use Queueable, SerializesModels;

	private array $data;
	
    /**
     * Create a new message instance.
     */
    public function __construct(array $data){
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have received an invoice',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view : 'emails.send_invoice',
			with : [
				'mail_data'	=>	$this->data
			]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
		$local_attachments = [];
		foreach($this->data['attachments'] as $attachment){
			$local_attachments[] = Attachment::fromStorageDisk($attachment['disk'], $attachment['path']);
		}

        return $local_attachments;

    }

}
