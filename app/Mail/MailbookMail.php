<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class MailbookMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('laravel@mailbook.dev', 'Mailbook'),
            subject: 'Welcome to Mailbook!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.mailbook',
        );
    }
}
