<?php

declare(strict_types=1);

namespace App\Mail\Registration;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

final class NewMemberWelcome extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $memberName,
    ) {}

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Mailable-Class' => self::class,
            ],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welkom bij Almere Centraal!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-member-welcome',
            with: [
                'memberName' => $this->memberName,
            ],
        );
    }
}
