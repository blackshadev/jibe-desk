<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\MemberTagImportReport;
use Tests\UnitTestCase;

final class MemberTagImportReportTest extends UnitTestCase
{
    public function test_it_stores_the_imported_tag_count(): void
    {
        $subject = new MemberTagImportReport(importedTags: 78);

        static::assertSame(78, $subject->importedTags);
    }
}
