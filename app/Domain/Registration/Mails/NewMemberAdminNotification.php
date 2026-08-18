<?php

declare(strict_types=1);

namespace App\Domain\Registration\Mails;

use App\Domain\Mail\BaseMail;
use App\Domain\Mail\Recipient;
use App\Domain\Members\MemberId;
use App\Domain\Registration\MembershipData;
/** @phpstan-ignore domain.dependency */
use App\Filament\Admin\Resources\Members\MemberResource;
use Illuminate\Mail\Mailables\Content;
use Override;

final readonly class NewMemberAdminNotification extends BaseMail
{
    public function __construct(
        public MemberId $memberId,
        public string $memberName,
        public MembershipData $membershipData,
        public Recipient $recipient,
    ) {}

    #[Override]
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-member-admin-notification',
            with: [
                'memberName' => $this->memberName,
                'membershipData' => $this->membershipData,
                'editUrl' => MemberResource::getUrl('edit', ['record' => $this->memberId->value]),
            ],
        );
    }

    #[Override]
    public function subject(): string
    {
        return 'Nieuwe aanmelding: ' . $this->memberName;
    }

    #[Override]
    public function to(): Recipient
    {
        return $this->recipient;
    }
}
