<?php

declare(strict_types=1);

namespace App\Domain\Registration\Mails;

use App\Domain\Mail\BaseMail;
use App\Domain\Mail\Recipient;
use App\Domain\Members\MemberId;
use App\Domain\Registration\MembershipData;
use Illuminate\Mail\Mailables\Content;

final readonly class NewMemberAdminNotification extends BaseMail
{
    public function __construct(
        public MemberId $memberId,
        public string $memberName,
        public MembershipData $membershipData,
        public Recipient $recipient,
    ) {}

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-member-admin-notification',
            with: [
                'memberName' => $this->memberName,
                'membershipData' => $this->membershipData,
                'editUrl' => route('filament.admin.resources.members.edit', ['record' => $this->memberId->value]),
            ],
        );
    }

    public function subject(): string
    {
        return 'Nieuwe aanmelding: ' . $this->memberName;
    }

    public function to(): Recipient
    {
        return $this->recipient;
    }
}
