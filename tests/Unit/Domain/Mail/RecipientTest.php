<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail;

use App\Domain\Mail\Recipient;
use Tests\UnitTestCase;

final class RecipientTest extends UnitTestCase
{
    public function test_it_stores_name_and_email(): void
    {
        $recipient = new Recipient('Jan de Vries', 'jan@example.com');

        static::assertSame('Jan de Vries', $recipient->name);
        static::assertSame('jan@example.com', $recipient->email);
    }

    public function test_it_allows_empty_name(): void
    {
        $recipient = new Recipient('', 'jan@example.com');

        static::assertSame('', $recipient->name);
        static::assertSame('jan@example.com', $recipient->email);
    }
}
