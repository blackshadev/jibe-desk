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
        return self::report(
            rows: self::rows($allMembers),
            continuingRows: self::rows($continuingMembers),
            householdsCreated: $householdsCreated,
            mandatesCreated: count($allMembers),
            billingInstancesCreated: $billingInstancesCreated,
            erelidMembershipCreated: $erelidMembershipCreated,
        );
    }

    /**
     * @param list<MappedMemberRow> $rows
     */
    public function preview(array $rows): MemberImportReport
    {
        return self::report(
            rows: $rows,
            continuingRows: self::continuingRows($rows),
            householdsCreated: 0,
            mandatesCreated: 0,
            billingInstancesCreated: 0,
            erelidMembershipCreated: false,
        );
    }

    /**
     * @param list<MappedMemberRow> $rows
     * @param list<MappedMemberRow> $continuingRows
     */
    private static function report(
        array $rows,
        array $continuingRows,
        int $householdsCreated,
        int $mandatesCreated,
        int $billingInstancesCreated,
        bool $erelidMembershipCreated,
    ): MemberImportReport {
        return new MemberImportReport(
            importedMembers: count($continuingRows),
            stoppedMembers: count($rows) - count($continuingRows),
            householdsCreated: $householdsCreated,
            mandatesCreated: $mandatesCreated,
            billingInstancesCreated: $billingInstancesCreated,
            erelidMembershipCreated: $erelidMembershipCreated,
            leavers: self::reportRows(self::leavers($rows)),
            rowsWithoutMembershipType: self::reportRows(self::rowsWithoutMembershipType($rows)),
            emailMismatches: self::emailMismatches($rows),
        );
    }

    /**
     * @param list<CreatedMember> $members
     * @return list<MappedMemberRow>
     */
    private static function rows(array $members): array
    {
        return array_map(static fn (CreatedMember $member): MappedMemberRow => $member->row, $members);
    }

    /**
     * @param list<MappedMemberRow> $rows
     * @return list<MappedMemberRow>
     */
    private static function continuingRows(array $rows): array
    {
        return array_values(array_filter($rows, static fn (MappedMemberRow $row): bool => !$row->isLeaver()));
    }

    /**
     * @param list<MappedMemberRow> $rows
     * @return list<MappedMemberRow>
     */
    private static function leavers(array $rows): array
    {
        return array_values(array_filter($rows, static fn (MappedMemberRow $row): bool => $row->isLeaver()));
    }

    /**
     * @param list<MappedMemberRow> $rows
     * @return list<MappedMemberRow>
     */
    private static function rowsWithoutMembershipType(array $rows): array
    {
        return array_values(array_filter($rows, static fn (MappedMemberRow $row): bool => !$row->hasMembershipType()));
    }

    /** @param list<MappedMemberRow> $rows @return list<MemberImportReportRow> */
    private static function reportRows(array $rows): array
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
     * @param list<MappedMemberRow> $rows
     * @return list<MemberImportEmailMismatch>
     */
    private static function emailMismatches(array $rows): array
    {
        $mismatches = [];

        foreach ($rows as $row) {
            if ($row->contactEmail === null) {
                continue;
            }

            if (strcasecmp($row->email, $row->contactEmail) === 0) {
                continue;
            }

            $mismatches[] = new MemberImportEmailMismatch(
                lidnr: $row->lidnr,
                name: $row->fullName,
                email: $row->email,
            );
        }

        return $mismatches;
    }
}
