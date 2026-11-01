<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Infrastructure\Bookkeeping\CostCenters\Import\VerdichtingRecord;
use Tests\UnitTestCase;

final class VerdichtingRecordTest extends UnitTestCase
{
    public function test_it_stores_the_verdichting_row(): void
    {
        $subject = new VerdichtingRecord(
            lineNumber: 3,
            number: '10',
            name: 'materiaal',
            parentNumber: '20',
            parentName: 'Kosten',
        );

        static::assertSame(3, $subject->lineNumber);
        static::assertSame('10', $subject->number);
        static::assertSame('materiaal', $subject->name);
        static::assertSame('20', $subject->parentNumber);
        static::assertSame('Kosten', $subject->parentName);
    }
}
