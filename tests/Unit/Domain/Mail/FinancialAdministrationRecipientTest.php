<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail;

use App\Domain\Mail\FinancialAdministrationRecipient;
use App\Domain\Mail\Recipient;
use Tests\UnitTestCase;

final class FinancialAdministrationRecipientTest extends UnitTestCase
{
    public function test_it_stores_the_recipient(): void
    {
        $recipient = new Recipient('Penningmeester', 'finance@example.com');

        $admin = new FinancialAdministrationRecipient($recipient);

        static::assertSame($recipient, $admin->recipient);
    }
}
