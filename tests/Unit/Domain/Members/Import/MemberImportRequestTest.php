<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\MemberImportRequest;
use DateTimeImmutable;
use Tests\UnitTestCase;

final class MemberImportRequestTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $billingStart = new DateTimeImmutable('2027-01-01');
        $lastCollected = new DateTimeImmutable('2026-12-31');

        $subject = new MemberImportRequest(
            path: '/tmp/leden.xlsx',
            dryRun: true,
            billingStart: $billingStart,
            lastCollected: $lastCollected,
        );

        static::assertSame('/tmp/leden.xlsx', $subject->path);
        static::assertTrue($subject->dryRun);
        static::assertSame($billingStart, $subject->billingStart);
        static::assertSame($lastCollected, $subject->lastCollected);
    }
}
