<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Mails;

use App\Domain\Mail\Recipient;
use App\Domain\Members\Mails\MemberStopped;
use Illuminate\Mail\Mailables\Content;
use Tests\UnitTestCase;

final class MemberStoppedTest extends UnitTestCase
{
    public function test_it_exposes_the_recipient(): void
    {
        $recipient = new Recipient('Vries, Jan de', 'jan@example.com');

        $mail = new MemberStopped($recipient, '31-12-2026', false);

        static::assertSame($recipient, $mail->to());
    }

    public function test_subject_is_the_stop_message(): void
    {
        $mail = new MemberStopped(new Recipient('Vries, Jan de', 'jan@example.com'), '31-12-2026', false);

        static::assertSame('Je lidmaatschap bij Watersportvereniging Almere Centraal is stopgezet', $mail->subject());
    }

    public function test_content_uses_the_stop_template_and_passes_the_payload(): void
    {
        $mail = new MemberStopped(new Recipient('Vries, Jan de', 'jan@example.com'), '31-12-2026', true);

        $content = $mail->content();

        static::assertInstanceOf(Content::class, $content);
        static::assertSame('mail.member-stopped', $content->markdown);
        static::assertSame('Vries, Jan de', $content->with['memberName']);
        static::assertSame('31-12-2026', $content->with['stopDate']);
        static::assertTrue($content->with['isImmediate']);
    }

    public function test_related_is_null_by_default(): void
    {
        $mail = new MemberStopped(new Recipient('Vries, Jan de', 'jan@example.com'), '31-12-2026', false);

        static::assertNull($mail->related());
    }
}
