<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\LegacyCsvImportException;
use App\Domain\Members\Import\StorageSpaceRentalImport;
use App\Domain\Members\Import\StorageSpaceRentalImportReport;
use App\Domain\Members\Import\StorageSpaceRentalImportRequest;
use App\Models\BillableItemInstance;
use App\Models\Member;
use App\Models\StorageSpace;
use App\Models\StorageSpaceLocation;
use App\Models\StorageSpaceRental;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;
use Throwable;

final readonly class StorageSpaceRentalImportImpl implements StorageSpaceRentalImport
{
    private const array REQUIRED_HEADERS = ['lidnr', 'objectnummer', 'start_date', 'end_date'];

    public function __construct(
        private LegacyCsvReader $reader,
        private LegacyCsvRecordMapper $recordMapper,
    ) {}

    #[Override]
    public function import(StorageSpaceRentalImportRequest $request): StorageSpaceRentalImportReport
    {
        $spreadsheetRows = $this->reader->read($request->path, self::REQUIRED_HEADERS);
        $records = $this->recordMapper->map($spreadsheetRows, 'objectnummer');
        $report = null;

        DB::beginTransaction();

        try {
            $report = $this->write($records, $request->billingStart);

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

    /** @param list<LegacyCsvRecord> $records */
    private function write(array $records, DateTimeInterface $billingStart): StorageSpaceRentalImportReport
    {
        $membersByLidnr = $this->membersByLidnr($records);
        $objectNumbers = $this->objectNumbers($records);
        $locationIdsByName = $this->locationIdsByName($objectNumbers);
        $storageSpacesByLocationId = $this->storageSpacesByLocationId($locationIdsByName);

        $seenAssignments = [];
        $rentalIds = [];

        foreach ($records as $index => $record) {
            $member = $membersByLidnr[$record->lidnr] ?? throw LegacyCsvImportException::unknownLidnr($record->rowNumber, $record->lidnr);
            $objectNumber = $objectNumbers[$index];

            $this->assertNotDuplicated($seenAssignments, $record);

            $storageSpace = $this->storageSpace($objectNumber, $record, $locationIdsByName, $storageSpacesByLocationId);

            $this->assertNoOverlap($storageSpace, $record);
            $this->assertMemberIsActive($member, $record);

            $rental = StorageSpaceRental::query()->create([
                'storage_space_id' => $storageSpace->id,
                'member_id' => $member->id,
                'start_date' => $record->startDate,
                'end_date' => $record->endDate,
            ]);

            $seenAssignments[$this->assignmentKey($record)] = true;
            $rentalIds[] = $rental->id;
        }

        $billingInstancesShifted = $this->shiftBillingStart($rentalIds, $billingStart);

        return new StorageSpaceRentalImportReport(
            importedRentals: count($rentalIds),
            billingInstancesShifted: $billingInstancesShifted,
        );
    }

    /**
     * @param list<LegacyCsvRecord> $records
     * @return array<string, Member>
     */
    private function membersByLidnr(array $records): array
    {
        $lidnrs = array_map(static fn (LegacyCsvRecord $record): string => $record->lidnr, $records);

        return Member::query()
            ->whereIn('lidnr', array_values(array_unique($lidnrs)))
            ->get()
            ->keyBy(static fn (Member $member): string => (string) $member->lidnr)
            ->all();
    }

    /**
     * @param list<LegacyCsvRecord> $records
     * @return list<StorageSpaceObjectNumber>
     */
    private function objectNumbers(array $records): array
    {
        return array_map(
            static fn (LegacyCsvRecord $record): StorageSpaceObjectNumber => StorageSpaceObjectNumber::from(
                $record->number,
                $record->rowNumber,
                $record->lidnr,
            ),
            $records,
        );
    }

    /**
     * @param list<StorageSpaceObjectNumber> $objectNumbers
     * @return array<string, int>
     */
    private function locationIdsByName(array $objectNumbers): array
    {
        $locationNames = array_values(array_unique(array_map(
            static fn (StorageSpaceObjectNumber $objectNumber): string => $objectNumber->locationName,
            $objectNumbers,
        )));

        $locationIds = StorageSpaceLocation::query()
            ->whereIn('name', $locationNames)
            ->pluck('id', 'name');

        return $locationIds
            ->map(static fn (int $id): int => $id)
            ->all();
    }

    /**
     * @param array<string, int> $locationIdsByName
     * @return array<string, array<string, StorageSpace>>
     */
    private function storageSpacesByLocationId(array $locationIdsByName): array
    {
        return StorageSpace::query()
            ->whereIn('storage_space_location_id', array_values($locationIdsByName))
            ->get()
            ->groupBy(static fn (StorageSpace $storageSpace): string => (string) $storageSpace->storage_space_location_id)
            ->map(static fn (Collection $storageSpaces): array => $storageSpaces
                ->keyBy(static fn (StorageSpace $storageSpace): string => (string) $storageSpace->number)
                ->all())
            ->all();
    }

    /**
     * @param array<string, int> $locationIdsByName
     * @param array<string, array<string, StorageSpace>> $storageSpacesByLocationId
     *
     * @throws LegacyCsvImportException
     */
    private function storageSpace(
        StorageSpaceObjectNumber $objectNumber,
        LegacyCsvRecord $record,
        array $locationIdsByName,
        array $storageSpacesByLocationId,
    ): StorageSpace {
        $locationId = $locationIdsByName[$objectNumber->locationName] ?? throw LegacyCsvImportException::unknownStorageSpaceLocation(
            $record->rowNumber,
            $record->lidnr,
            $objectNumber->locationName,
        );

        return $storageSpacesByLocationId[(string) $locationId][$objectNumber->number] ?? throw LegacyCsvImportException::unknownStorageSpace(
            $record->rowNumber,
            $record->lidnr,
            $record->number,
        );
    }

    /** @param array<string, true> $seenAssignments */
    private function assertNotDuplicated(array $seenAssignments, LegacyCsvRecord $record): void
    {
        if (!array_key_exists($this->assignmentKey($record), $seenAssignments)) {
            return;
        }

        throw LegacyCsvImportException::duplicateRecord($record->rowNumber, $record->lidnr, $record->number);
    }

    private function assertNoOverlap(StorageSpace $storageSpace, LegacyCsvRecord $record): void
    {
        $overlaps = StorageSpaceRental::query()
            ->overlapping(
                storageSpaceId: $storageSpace->id,
                startDate: $record->startDate,
                endDate: $record->endDate,
            )
            ->exists();

        if (!$overlaps) {
            return;
        }

        throw LegacyCsvImportException::overlappingRental($record->rowNumber, $record->lidnr, $record->number);
    }

    private function assertMemberIsActive(Member $member, LegacyCsvRecord $record): void
    {
        if (!$record->isOpenEnded() || $member->stopped_at === null) {
            return;
        }

        throw LegacyCsvImportException::openEndForStoppedMember($record->rowNumber, $record->lidnr, 'rental');
    }

    /**
     * Ended rentals keep their historical dates. Open and future rentals start
     * billing on the cutover date.
     *
     * @param list<int> $rentalIds
     */
    private function shiftBillingStart(array $rentalIds, DateTimeInterface $billingStart): int
    {
        $instanceIds = StorageSpaceRental::query()
            ->whereIn('id', $rentalIds)
            ->pluck('billable_item_instance_id');

        $instancesToShift = BillableItemInstance::query()
            ->whereIn('id', $instanceIds)
            ->where(static fn (Builder $query): Builder => $query
                ->whereNull('end_date')
                ->orWhere('end_date', '>', $billingStart))
            ->pluck('id');

        BillableItemInstance::query()
            ->whereIn('id', $instancesToShift)
            ->update(['start_date' => $billingStart]);

        return $instancesToShift->count();
    }

    private function assignmentKey(LegacyCsvRecord $record): string
    {
        return sprintf('%s|%s', $record->lidnr, $record->number);
    }
}
