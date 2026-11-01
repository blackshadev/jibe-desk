<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImport;
use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportReport;
use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportRequest;
use App\Models\CostCenter;
use App\Models\CostCenterGroup;
use Illuminate\Support\Facades\DB;
use Override;
use RuntimeException;
use Throwable;

final readonly class CostCenterImportImpl implements CostCenterImport
{
    public function __construct(
        private GrootboekRecordMapper $grootboekMapper,
        private VerdichtingRecordMapper $verdichtingMapper,
        private HoofdverdichtingRecordMapper $hoofdverdichtingMapper,
        private CostCenterImportReportBuilder $reportBuilder,
    ) {}

    #[Override]
    public function import(CostCenterImportRequest $request): CostCenterImportReport
    {
        $grootboek = $this->grootboekMapper->map($request->grootboekPath);
        $verdichtingen = $this->verdichtingMapper->map($request->verdichtingenPath);
        $hoofdverdichtingen = $this->hoofdverdichtingMapper->map($request->hoofdverdichtingenPath);

        $hierarchy = new CostCenterHierarchy($hoofdverdichtingen, $verdichtingen);
        $hierarchy->assertAccountsBelong($grootboek);

        if ($request->validateOnly) {
            return $this->reportBuilder->build($grootboek, $hierarchy, 0, 0, 0, 0);
        }

        DB::beginTransaction();

        try {
            $report = $this->write($grootboek, $hierarchy);

            if ($request->dryRun) {
                DB::rollBack();

                return $report;
            }

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        return $report;
    }

    /**
     * @param list<GrootboekRecord> $grootboek
     */
    private function write(array $grootboek, CostCenterHierarchy $hierarchy): CostCenterImportReport
    {
        $groupsCreated = 0;
        $groupsUpdated = 0;
        $groupIds = [];

        foreach ($hierarchy->groups() as $record) {
            $group = CostCenterGroup::query()->firstOrNew(['number' => $record->number]);
            $isNew = !$group->exists;

            $group->external_number = $record->externalNumber;
            $group->name = $record->name;
            $group->parent_id = $record->parentNumber === null
                ? null
                : $this->idFor($groupIds, $record->parentNumber);

            if ($isNew || $group->isDirty()) {
                $group->save();

                $groupsCreated += (int) $isNew;
                $groupsUpdated += (int) !$isNew;
            }

            $groupIds[$record->number] = $group->id;
        }

        $costCentersCreated = 0;
        $costCentersUpdated = 0;

        foreach ($grootboek as $record) {
            $groupNumber = $hierarchy->groupNumberForVerdichting($record->verdichtingNumber);
            $costCenter = CostCenter::query()->firstOrNew(['number' => $record->accountNumber]);
            $isNew = !$costCenter->exists;

            $costCenter->title = $record->title;
            $costCenter->cost_center_group_id = $this->idFor($groupIds, $groupNumber);

            if ($isNew || $costCenter->isDirty()) {
                $costCenter->save();

                $costCentersCreated += (int) $isNew;
                $costCentersUpdated += (int) !$isNew;
            }
        }

        return $this->reportBuilder->build(
            $grootboek,
            $hierarchy,
            $costCentersCreated,
            $costCentersUpdated,
            $groupsCreated,
            $groupsUpdated,
        );
    }

    /**
     * @param array<string, int> $ids
     */
    private function idFor(array $ids, string $number): int
    {
        return $ids[$number] ?? throw new RuntimeException(sprintf(
            'The group "%s" was not created before its child.',
            $number,
        ));
    }
}
