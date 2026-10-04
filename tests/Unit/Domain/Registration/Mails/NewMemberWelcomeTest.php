<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration\Mails;

use App\Domain\Mail\Recipient;
use App\Domain\Registration\Mails\NewMemberWelcome;
use Illuminate\Mail\Mailables\Content;
use Tests\UnitTestCase;

final class NewMemberWelcomeTest extends UnitTestCase
{
    private const SET_PASSWORD_URL = 'https://example.test/reset-password/abc?email=jan%40example.com';

    public function test_it_exposes_the_recipient(): void
    {
        $recipient = new Recipient('Vries, Jan de', 'jan@example.com');

        $mail = new NewMemberWelcome($recipient, self::SET_PASSWORD_URL);

        static::assertSame($recipient, $mail->to());
    }

    public function test_subject_is_the_welcome_message(): void
    {
        $mail = new NewMemberWelcome(
            new Recipient('Vries, Jan de', 'jan@example.com'),
            self::SET_PASSWORD_URL,
        );

        static::assertSame('Welkom bij Almere Centraal!', $mail->subject());
    }

    public function test_content_uses_the_welcome_template_and_passes_the_member_name_and_url(): void
    {
        $mail = new NewMemberWelcome(
            new Recipient('Vries, Jan de', 'jan@example.com'),
            self::SET_PASSWORD_URL,
        );

        $content = $mail->content();

        static::assertInstanceOf(Content::class, $content);
        static::assertSame('mail.new-member-welcome', $content->markdown);
        static::assertSame('Vries, Jan de', $content->with['memberName']);
        static::assertSame(self::SET_PASSWORD_URL, $content->with['setPasswordUrl']);
    }

    public function test_related_is_null_by_default(): void
    {
        $mail = new NewMemberWelcome(
            new Recipient('Vries, Jan de', 'jan@example.com'),
            self::SET_PASSWORD_URL,
        );

        static::assertNull($mail->related());
    }
}
