<?php

declare(strict_types=1);

namespace App\Domain\Members\Mails;

use App\Domain\Mail\BaseMail;
use App\Domain\Mail\Recipient;
use Illuminate\Mail\Mailables\Content;
use Override;

final readonly class MemberStopped extends BaseMail
{
    public function __construct(
        public Recipient $recipient,
        public string $stopDate,
        public bool $isImmediate,
    ) {}

    #[Override]
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.member-stopped',
            with: [
                'memberName' => $this->recipient->name,
                'stopDate' => $this->stopDate,
                'isImmediate' => $this->isImmediate,
            ],
        );
    }

    #[Override]
    public function subject(): string
    {
        return 'Je lidmaatschap bij Watersportvereniging Almere Centraal wordt stopgezet';
    }

    #[Override]
    public function to(): Recipient
    {
        return $this->recipient;
    }
}
