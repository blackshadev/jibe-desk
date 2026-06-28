<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail;

use App\Domain\Mail\MemberAdministrationRecipient;
use App\Domain\Mail\Recipient;
use Tests\UnitTestCase;

final class MemberAdministrationRecipientTest extends UnitTestCase
{
    public function test_it_stores_the_recipient(): void
    {
        $recipient = new Recipient('Ledenadministratie', 'admin@example.com');

        $admin = new MemberAdministrationRecipient($recipient);

        static::assertSame($recipient, $admin->recipient);
    }
}
