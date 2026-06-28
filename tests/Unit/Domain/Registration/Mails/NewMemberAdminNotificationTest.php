<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration\Mails;

use App\Domain\Mail\Recipient;
use App\Domain\Members\MemberId;
use App\Domain\Registration\Mails\NewMemberAdminNotification;
use App\Domain\Registration\MembershipData;
use Illuminate\Mail\Mailables\Content;
use Tests\FeatureTestCase;

final class NewMemberAdminNotificationTest extends FeatureTestCase
{
    public function test_it_exposes_the_recipient(): void
    {
        $recipient = new Recipient('Admin', 'admin@example.com');
        $mail = new NewMemberAdminNotification(
            MemberId::create(7),
            'Vries, Jan de',
            MembershipData::createDefault(),
            $recipient,
        );

        static::assertSame($recipient, $mail->to());
    }

    public function test_subject_is_the_new_member_name(): void
    {
        $mail = new NewMemberAdminNotification(
            MemberId::create(7),
            'Vries, Jan de',
            MembershipData::createDefault(),
            new Recipient('Admin', 'admin@example.com'),
        );

        static::assertSame('Nieuwe aanmelding: Vries, Jan de', $mail->subject());
    }

    public function test_content_uses_the_admin_notification_template_and_passes_the_data(): void
    {
        $memberName = 'Vries, Jan de';
        $membershipData = MembershipData::createDefault();

        $mail = new NewMemberAdminNotification(
            MemberId::create(7),
            $memberName,
            $membershipData,
            new Recipient('Admin', 'admin@example.com'),
        );

        $content = $mail->content();

        static::assertInstanceOf(Content::class, $content);
        static::assertSame('mail.new-member-admin-notification', $content->markdown);
        static::assertSame($memberName, $content->with['memberName']);
        static::assertSame($membershipData, $content->with['membershipData']);
        static::assertArrayHasKey('editUrl', $content->with);
    }

    public function test_related_is_null_by_default(): void
    {
        $mail = new NewMemberAdminNotification(
            MemberId::create(7),
            'Vries, Jan de',
            MembershipData::createDefault(),
            new Recipient('Admin', 'admin@example.com'),
        );

        static::assertNull($mail->related());
    }
}
