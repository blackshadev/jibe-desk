<?php

declare(strict_types=1);

namespace App\Domain\Members\Import;

final readonly class MemberImportReport
{
    /**
     * @param list<MemberImportReportRow> $leavers
     * @param list<MemberImportReportRow> $rowsWithoutMembershipType
     * @param list<MemberImportEmailMismatch> $emailMismatches
     */
    public function __construct(
        public int $importedMembers,
        public int $stoppedMembers,
        public int $householdsCreated,
        public int $mandatesCreated,
        public int $billingInstancesCreated,
        public bool $erelidMembershipCreated,
        public array $leavers,
        public array $rowsWithoutMembershipType,
        public array $emailMismatches,
    ) {}
}
