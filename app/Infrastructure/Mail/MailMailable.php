<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Mail\BaseMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

final class MailMailable extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        private BaseMail $mail,
    ) {}

    public function content(): Content
    {
        return $this->mail->content();
    }

    public function headers(): Headers
    {
        $related = $this->mail->related();
        $headers = [
            'X-Mail-Class' => get_class($this->mail),
        ];

        if ($related) {
            $headers['X-Related-Model'] = $related->class;
            $headers['X-Related-Id'] = $related->id;
        }
        return new Headers(
            text: $headers,
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mail->subject(),
        );
    }
}
