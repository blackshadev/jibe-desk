<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration\Mails;

use App\Domain\Mail\Recipient;
use App\Domain\Registration\Mails\NewMemberWelcome;
use Illuminate\Mail\Mailables\Content;
use Tests\UnitTestCase;

final class NewMemberWelcomeTest extends UnitTestCase
{
    public function test_it_exposes_the_recipient(): void
    {
        $recipient = new Recipient('Vries, Jan de', 'jan@example.com');

        $mail = new NewMemberWelcome($recipient);

        static::assertSame($recipient, $mail->to());
    }

    public function test_subject_is_the_welcome_message(): void
    {
        $mail = new NewMemberWelcome(
            new Recipient('Vries, Jan de', 'jan@example.com'),
        );

        static::assertSame('Welkom bij Almere Centraal!', $mail->subject());
    }

    public function test_content_uses_the_welcome_template_and_passes_the_member_name(): void
    {
        $mail = new NewMemberWelcome(
            new Recipient('Vries, Jan de', 'jan@example.com'),
        );

        $content = $mail->content();

        static::assertInstanceOf(Content::class, $content);
        static::assertSame('mail.new-member-welcome', $content->markdown);
        static::assertSame('Vries, Jan de', $content->with['memberName']);
    }

    public function test_related_is_null_by_default(): void
    {
        $mail = new NewMemberWelcome(
            new Recipient('Vries, Jan de', 'jan@example.com'),
        );

        static::assertNull($mail->related());
    }
}
