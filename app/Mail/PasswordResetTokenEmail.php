<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetTokenEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $token)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Password Reset Token',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'UserMail.PasswordResetTokenEmail',
            with: [
                'token' => $this->token,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
