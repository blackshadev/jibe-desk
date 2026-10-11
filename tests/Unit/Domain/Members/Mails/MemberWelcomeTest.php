<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Mails;

use App\Domain\Mail\Recipient;
use App\Domain\Members\Mails\MemberWelcome;
use Illuminate\Mail\Mailables\Content;
use Tests\UnitTestCase;

final class MemberWelcomeTest extends UnitTestCase
{
    private const SET_PASSWORD_URL = 'https://example.test/reset-password/abc?email=jan%40example.com';

    private const LOGIN_URL = 'https://example.test/admin/login';

    public function test_it_exposes_the_recipient(): void
    {
        $recipient = new Recipient('Vries, Jan de', 'jan@example.com');

        $mail = new MemberWelcome($recipient, self::SET_PASSWORD_URL, self::LOGIN_URL);

        static::assertSame($recipient, $mail->to());
    }

    public function test_subject_is_the_welcome_message(): void
    {
        $mail = new MemberWelcome(
            new Recipient('Vries, Jan de', 'jan@example.com'),
            self::SET_PASSWORD_URL,
            self::LOGIN_URL,
        );

        static::assertSame('Welkom als lid bij Watersportvereniging Almere Centraal!', $mail->subject());
    }

    public function test_content_uses_the_welcome_template_and_passes_the_payload(): void
    {
        $mail = new MemberWelcome(
            new Recipient('Vries, Jan de', 'jan@example.com'),
            self::SET_PASSWORD_URL,
            self::LOGIN_URL,
        );

        $content = $mail->content();

        static::assertInstanceOf(Content::class, $content);
        static::assertSame('mail.member-welcome', $content->markdown);
        static::assertSame('Vries, Jan de', $content->with['memberName']);
        static::assertSame(self::SET_PASSWORD_URL, $content->with['setPasswordUrl']);
        static::assertSame(self::LOGIN_URL, $content->with['loginUrl']);
    }

    public function test_related_is_null_by_default(): void
    {
        $mail = new MemberWelcome(
            new Recipient('Vries, Jan de', 'jan@example.com'),
            self::SET_PASSWORD_URL,
            self::LOGIN_URL,
        );

        static::assertNull($mail->related());
    }
}
