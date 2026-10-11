<?php

declare(strict_types=1);

namespace App\Domain\Members\Mails;

use App\Domain\Mail\BaseMail;
use App\Domain\Mail\Recipient;
use Illuminate\Mail\Mailables\Content;
use Override;

final readonly class MemberWelcome extends BaseMail
{
    public function __construct(
        public Recipient $recipient,
        public string $setPasswordUrl,
        public string $loginUrl,
    ) {}

    #[Override]
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.member-welcome',
            with: [
                'memberName' => $this->recipient->name,
                'setPasswordUrl' => $this->setPasswordUrl,
                'loginUrl' => $this->loginUrl,
            ],
        );
    }

    #[Override]
    public function subject(): string
    {
        return 'Welkom als lid bij Watersportvereniging Almere Centraal!';
    }

    #[Override]
    public function to(): Recipient
    {
        return $this->recipient;
    }
}
