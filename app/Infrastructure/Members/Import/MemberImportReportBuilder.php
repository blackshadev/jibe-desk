<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\MemberImportEmailMismatch;
use App\Domain\Members\Import\MemberImportReport;
use App\Domain\Members\Import\MemberImportReportRow;

final readonly class MemberImportReportBuilder
{
    /**
     * @param list<CreatedMember> $allMembers
     * @param list<CreatedMember> $continuingMembers
     */
    public function build(
        array $allMembers,
        array $continuingMembers,
        int $householdsCreated,
        int $billingInstancesCreated,
        bool $erelidMembershipCreated,
    ): MemberImportReport {
        $rowsWithoutMembershipType = $this->reportRows(
            $this->filterByRow($allMembers, static fn (MappedMemberRow $row): bool => !$row->hasMembershipType()),
        );

        return new MemberImportReport(
            importedMembers: count($continuingMembers),
            stoppedMembers: count($allMembers) - count($continuingMembers),
            householdsCreated: $householdsCreated,
            mandatesCreated: count($continuingMembers),
            billingInstancesCreated: $billingInstancesCreated,
            erelidMembershipCreated: $erelidMembershipCreated,
            leavers: $this->reportRows(
                $this->filterByRow($allMembers, static fn (MappedMemberRow $row): bool => $row->isLeaver()),
            ),
            rtcMembers: $this->reportRows(
                $this->filterByRow($allMembers, static fn (MappedMemberRow $row): bool => $row->attendsRtc),
            ),
            containerMembers: $this->reportRows(
                $this->filterByRow($allMembers, static fn (MappedMemberRow $row): bool => $row->hasContainer),
            ),
            rowsWithoutMembershipType: $rowsWithoutMembershipType,
            rowsDefaultedToWindsurfer: $rowsWithoutMembershipType,
            emailMismatches: $this->emailMismatches($allMembers),
        );
    }

    /**
     * @param list<CreatedMember> $members
     * @param callable(MappedMemberRow): bool $predicate
     * @return list<MappedMemberRow>
     */
    private function filterByRow(array $members, callable $predicate): array
    {
        return array_values(array_map(
            static fn (CreatedMember $member): MappedMemberRow => $member->row,
            array_filter($members, static fn (CreatedMember $member): bool => $predicate($member->row)),
        ));
    }

    /** @param list<MappedMemberRow> $rows @return list<MemberImportReportRow> */
    private function reportRows(array $rows): array
    {
        return array_map(
            static fn (MappedMemberRow $row): MemberImportReportRow => new MemberImportReportRow(
                lidnr: $row->lidnr,
                name: $row->fullName,
                email: $row->email,
            ),
            $rows,
        );
    }

    /**
     * @param list<CreatedMember> $members
     * @return list<MemberImportEmailMismatch>
     */
    private function emailMismatches(array $members): array
    {
        $mismatches = [];

        foreach ($members as $member) {
            $row = $member->row;

            if ($row->contactEmail === null) {
                continue;
            }

            if (strcasecmp($row->email, $row->contactEmail) === 0) {
                continue;
            }

            $mismatches[] = new MemberImportEmailMismatch(
                lidnr: $row->lidnr,
                name: $row->fullName,
                privateEmail: $row->email,
                contactEmail: $row->contactEmail,
            );
        }

        return $mismatches;
    }
}
