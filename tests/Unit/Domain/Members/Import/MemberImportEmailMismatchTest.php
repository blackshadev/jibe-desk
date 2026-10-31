<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\MemberImportEmailMismatch;
use Tests\UnitTestCase;

final class MemberImportEmailMismatchTest extends UnitTestCase
{
    public function test_it_stores_the_member_email(): void
    {
        $subject = new MemberImportEmailMismatch(
            lidnr: '124175',
            name: 'Kick Fock',
            email: 'jimmyfock@hotmail.com',
        );

        static::assertSame('124175', $subject->lidnr);
        static::assertSame('Kick Fock', $subject->name);
        static::assertSame('jimmyfock@hotmail.com', $subject->email);
    }
}
