<?php

declare(strict_types=1);

namespace App\Domain\Registration\Mails;

use App\Domain\Mail\BaseMail;
use App\Domain\Mail\Recipient;
use Illuminate\Mail\Mailables\Content;

final readonly class NewMemberWelcome extends BaseMail
{
    public function __construct(
        public Recipient $recipient,
    ) {}

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-member-welcome',
            with: [
                'memberName' => $this->recipient->name,
            ],
        );
    }

    public function subject(): string
    {
        return 'Welkom bij Almere Centraal!';
    }

    public function to(): Recipient
    {
        return $this->recipient;
    }
}
