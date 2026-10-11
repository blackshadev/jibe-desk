<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration\Mails;

use App\Domain\Mail\Recipient;
use App\Domain\Registration\Mails\RegistrationReceived;
use Illuminate\Mail\Mailables\Content;
use Tests\UnitTestCase;

final class RegistrationReceivedTest extends UnitTestCase
{
    public function test_it_exposes_the_recipient(): void
    {
        $recipient = new Recipient('Vries, Jan de', 'jan@example.com');

        $mail = new RegistrationReceived($recipient);

        static::assertSame($recipient, $mail->to());
    }

    public function test_subject_is_the_acknowledgment_message(): void
    {
        $mail = new RegistrationReceived(
            new Recipient('Vries, Jan de', 'jan@example.com'),
        );

        static::assertSame('We hebben je inschrijving ontvangen!', $mail->subject());
    }

    public function test_content_uses_the_registration_received_template(): void
    {
        $mail = new RegistrationReceived(
            new Recipient('Vries, Jan de', 'jan@example.com'),
        );

        $content = $mail->content();

        static::assertInstanceOf(Content::class, $content);
        static::assertSame('mail.registration-received', $content->markdown);
        static::assertSame('Vries, Jan de', $content->with['memberName']);
    }

    public function test_related_is_null_by_default(): void
    {
        $mail = new RegistrationReceived(
            new Recipient('Vries, Jan de', 'jan@example.com'),
        );

        static::assertNull($mail->related());
    }
}
