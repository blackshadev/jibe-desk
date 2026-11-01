<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\MemberTagImportRequest;
use Tests\UnitTestCase;

final class MemberTagImportRequestTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $subject = new MemberTagImportRequest(path: '/tmp/tags.csv', dryRun: true);

        static::assertSame('/tmp/tags.csv', $subject->path);
        static::assertTrue($subject->dryRun);
    }
}
