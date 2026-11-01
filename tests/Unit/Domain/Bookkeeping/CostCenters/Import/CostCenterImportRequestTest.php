<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportRequest;
use Tests\UnitTestCase;

final class CostCenterImportRequestTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $subject = new CostCenterImportRequest(
            grootboekPath: '/tmp/grootboek.csv',
            verdichtingenPath: '/tmp/verdichtingen.csv',
            hoofdverdichtingenPath: '/tmp/hoofdverdichtingen.csv',
            dryRun: true,
            validateOnly: false,
        );

        static::assertSame('/tmp/grootboek.csv', $subject->grootboekPath);
        static::assertSame('/tmp/verdichtingen.csv', $subject->verdichtingenPath);
        static::assertSame('/tmp/hoofdverdichtingen.csv', $subject->hoofdverdichtingenPath);
        static::assertTrue($subject->dryRun);
        static::assertFalse($subject->validateOnly);
    }
}
