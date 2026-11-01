<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Infrastructure\Bookkeeping\CostCenters\Import\HoofdverdichtingRecord;
use Tests\UnitTestCase;

final class HoofdverdichtingRecordTest extends UnitTestCase
{
    public function test_it_stores_the_hoofdverdichting_row(): void
    {
        $subject = new HoofdverdichtingRecord(
            lineNumber: 4,
            number: '20',
            name: 'Kosten',
        );

        static::assertSame(4, $subject->lineNumber);
        static::assertSame('20', $subject->number);
        static::assertSame('Kosten', $subject->name);
    }
}
