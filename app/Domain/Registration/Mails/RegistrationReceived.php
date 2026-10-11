<?php

declare(strict_types=1);

namespace App\Domain\Registration\Mails;

use App\Domain\Mail\BaseMail;
use App\Domain\Mail\Recipient;
use Illuminate\Mail\Mailables\Content;
use Override;

final readonly class RegistrationReceived extends BaseMail
{
    public function __construct(
        public Recipient $recipient,
    ) {}

    #[Override]
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.registration-received',
            with: [
                'memberName' => $this->recipient->name,
            ],
        );
    }

    #[Override]
    public function subject(): string
    {
        return 'We hebben je inschrijving ontvangen!';
    }

    #[Override]
    public function to(): Recipient
    {
        return $this->recipient;
    }
}
