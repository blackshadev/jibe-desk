<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Infrastructure\Bookkeeping\CostCenters\Import\GrootboekRecord;
use Tests\UnitTestCase;

final class GrootboekRecordTest extends UnitTestCase
{
    public function test_it_stores_the_grootboek_row(): void
    {
        $subject = new GrootboekRecord(
            lineNumber: 2,
            accountNumber: '8105',
            title: 'Contributie leden',
            verdichtingNumber: '10',
            verdichtingName: 'contributie',
        );

        static::assertSame(2, $subject->lineNumber);
        static::assertSame('8105', $subject->accountNumber);
        static::assertSame('Contributie leden', $subject->title);
        static::assertSame('10', $subject->verdichtingNumber);
        static::assertSame('contributie', $subject->verdichtingName);
    }
}
