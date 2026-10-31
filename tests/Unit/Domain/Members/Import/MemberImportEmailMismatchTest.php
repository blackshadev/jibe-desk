<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\MemberImportEmailMismatch;
use Tests\UnitTestCase;

final class MemberImportEmailMismatchTest extends UnitTestCase
{
    public function test_it_stores_both_email_addresses(): void
    {
        $subject = new MemberImportEmailMismatch(
            lidnr: '124175',
            name: 'Kick Fock',
            privateEmail: 'jimmyfock@hotmail.com',
            contactEmail: 'Kickiefock@icloud.com',
        );

        static::assertSame('124175', $subject->lidnr);
        static::assertSame('Kick Fock', $subject->name);
        static::assertSame('jimmyfock@hotmail.com', $subject->privateEmail);
        static::assertSame('Kickiefock@icloud.com', $subject->contactEmail);
    }
}
