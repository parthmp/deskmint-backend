<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Request;

class SendResetPasswordEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

	private $reset_code_row = null;

    /**
     * Create a new message instance.
     */
    public function __construct($reset_code_row)
    {
        $this->reset_code_row = $reset_code_row;
    }

	public function getResetCode(){
		return $this->reset_code_row->reset_code;
	}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have requested for password reset',
			replyTo: [
                new Address(env("MAIL_REPLYTO_ADDRESS"), env("APP_NAME")),
            ]
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.password_reset',
			with: ['name' => $this->reset_code_row->user->name, 'reset_code' => $this->reset_code_row->reset_code, 'ip' => Request::ip()]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
