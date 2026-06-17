<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Members\MemberId;
use App\Domain\Registration\MembershipData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

final class NewMemberAdminNotification extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly MemberId $memberId,
        public readonly string $memberName,
        public readonly MembershipData $membershipData,
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
            subject: 'Nieuwe aanmelding: ' . $this->memberName,
        );
    }

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
}
