<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\MemberImportReportRow;
use Tests\UnitTestCase;

final class MemberImportReportRowTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $subject = new MemberImportReportRow('124038', 'Rene Houkema', 'rene@example.com');

        static::assertSame('124038', $subject->lidnr);
        static::assertSame('Rene Houkema', $subject->name);
        static::assertSame('rene@example.com', $subject->email);
    }
}
