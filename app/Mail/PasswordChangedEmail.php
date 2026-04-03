<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Password Changed Successfully',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'UserMail.PasswordChangedEmail',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
