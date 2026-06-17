<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail;

use App\Domain\Mail\OutgoingEmailStatus;
use Tests\UnitTestCase;

final class OutgoingEmailStatusTest extends UnitTestCase
{
    public function test_it_has_queued_status(): void
    {
        static::assertSame('queued', OutgoingEmailStatus::Queued->value);
    }

    public function test_it_has_sent_status(): void
    {
        static::assertSame('sent', OutgoingEmailStatus::Sent->value);
    }

    public function test_it_has_failed_status(): void
    {
        static::assertSame('failed', OutgoingEmailStatus::Failed->value);
    }

    public function test_it_has_exactly_three_cases(): void
    {
        static::assertCount(3, OutgoingEmailStatus::cases());
    }
}
