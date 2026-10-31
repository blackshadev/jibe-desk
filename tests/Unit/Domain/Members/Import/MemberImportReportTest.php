<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\MemberImportReport;
use App\Domain\Members\Import\MemberImportReportRow;
use Tests\UnitTestCase;

final class MemberImportReportTest extends UnitTestCase
{
    public function test_it_stores_all_counts_and_rows(): void
    {
        $row = new MemberImportReportRow('123', 'Jan Jansen', 'jan@example.com');

        $subject = new MemberImportReport(
            importedMembers: 186,
            stoppedMembers: 21,
            householdsCreated: 19,
            mandatesCreated: 186,
            billingInstancesCreated: 431,
            erelidMembershipCreated: true,
            leavers: [$row],
            rowsWithoutMembershipType: [$row],
            emailMismatches: [],
        );

        static::assertSame(186, $subject->importedMembers);
        static::assertSame(21, $subject->stoppedMembers);
        static::assertSame(19, $subject->householdsCreated);
        static::assertSame(186, $subject->mandatesCreated);
        static::assertSame(431, $subject->billingInstancesCreated);
        static::assertTrue($subject->erelidMembershipCreated);
        static::assertSame([$row], $subject->leavers);
        static::assertSame([$row], $subject->rowsWithoutMembershipType);
        static::assertSame([], $subject->emailMismatches);
    }
}
