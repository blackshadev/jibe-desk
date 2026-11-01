<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Infrastructure\Bookkeeping\CostCenters\Import\CostCenterGroupRecord;
use Tests\UnitTestCase;

final class CostCenterGroupRecordTest extends UnitTestCase
{
    public function test_it_stores_a_hoofdverdichting_group(): void
    {
        $subject = new CostCenterGroupRecord(
            number: '20',
            externalNumber: '20',
            name: 'Kosten',
            parentNumber: null,
        );

        static::assertSame('20', $subject->number);
        static::assertSame('20', $subject->externalNumber);
        static::assertSame('Kosten', $subject->name);
        static::assertNull($subject->parentNumber);
    }

    public function test_it_stores_a_verdichting_group(): void
    {
        $subject = new CostCenterGroupRecord(
            number: '20.10',
            externalNumber: '10',
            name: 'materiaal',
            parentNumber: '20',
        );

        static::assertSame('20.10', $subject->number);
        static::assertSame('10', $subject->externalNumber);
        static::assertSame('materiaal', $subject->name);
        static::assertSame('20', $subject->parentNumber);
    }
}
