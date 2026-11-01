<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportReport;
use App\Models\CostCenter;
use App\Models\CostCenterGroup;

final readonly class CostCenterImportReportBuilder
{
    /**
     * @param list<GrootboekRecord> $grootboek
     */
    public function build(
        array $grootboek,
        CostCenterHierarchy $hierarchy,
        int $costCentersCreated,
        int $costCentersUpdated,
        int $groupsCreated,
        int $groupsUpdated,
    ): CostCenterImportReport {
        $fileAccountNumbers = array_map(
            static fn (GrootboekRecord $record): string => $record->accountNumber,
            $grootboek,
        );

        $fileGroupNumbers = $hierarchy->groupNumbers();

        return new CostCenterImportReport(
            costCentersInFile: count($grootboek),
            groupsInFile: count($hierarchy->groups()),
            costCentersCreated: $costCentersCreated,
            costCentersUpdated: $costCentersUpdated,
            groupsCreated: $groupsCreated,
            groupsUpdated: $groupsUpdated,
            costCentersAbsentFromFiles: CostCenter::query()
                ->whereNotIn('number', $fileAccountNumbers)
                ->orderBy('number')
                ->pluck('number')
                ->map(static fn (mixed $number): string => (string) $number)
                ->all(),
            groupsAbsentFromFiles: CostCenterGroup::query()
                ->whereNotIn('number', $fileGroupNumbers)
                ->orderBy('number')
                ->pluck('number')
                ->map(static fn (mixed $number): string => (string) $number)
                ->all(),
        );
    }
}
