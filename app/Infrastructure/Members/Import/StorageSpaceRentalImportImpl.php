<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\CsvValueException;
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
        private CsvFileReader $reader,
        private MemberObjectCsvRecordMapper $recordMapper,
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

    /** @param list<MemberObjectCsvRecord> $records */
    private function write(array $records, DateTimeInterface $billingStart): StorageSpaceRentalImportReport
    {
        $membersByLidnr = $this->membersByLidnr($records);
        $objectNumbers = $this->objectNumbers($records);
        $locationIdsByName = $this->locationIdsByName($objectNumbers);
        $storageSpacesByLocationId = $this->storageSpacesByLocationId($locationIdsByName);

        $seenAssignments = [];
        $rentalIds = [];

        foreach ($records as $index => $record) {
            $member = $membersByLidnr[$record->memberNumber] ?? throw CsvValueException::unknownLidnr($record->rowNumber, $record->memberNumber);
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
     * @param list<MemberObjectCsvRecord> $records
     * @return array<string, Member>
     */
    private function membersByLidnr(array $records): array
    {
        $lidnrs = array_map(static fn (MemberObjectCsvRecord $record): string => $record->memberNumber, $records);

        return Member::query()
            ->whereIn('lidnr', array_values(array_unique($lidnrs)))
            ->get()
            ->keyBy(static fn (Member $member): string => (string) $member->lidnr)
            ->all();
    }

    /**
     * @param list<MemberObjectCsvRecord> $records
     * @return list<StorageSpaceObjectNumber>
     */
    private function objectNumbers(array $records): array
    {
        return array_map(
            static fn (MemberObjectCsvRecord $record): StorageSpaceObjectNumber => StorageSpaceObjectNumber::from(
                $record->objectNumber,
                $record->rowNumber,
                $record->memberNumber,
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
     * @throws CsvValueException
     */
    private function storageSpace(
        StorageSpaceObjectNumber $objectNumber,
        MemberObjectCsvRecord $record,
        array $locationIdsByName,
        array $storageSpacesByLocationId,
    ): StorageSpace {
        $locationId = $locationIdsByName[$objectNumber->locationName] ?? throw CsvValueException::unknownStorageSpaceLocation(
            $record->rowNumber,
            $record->memberNumber,
            $objectNumber->locationName,
        );

        return $storageSpacesByLocationId[(string) $locationId][$objectNumber->number] ?? throw CsvValueException::unknownStorageSpace(
            $record->rowNumber,
            $record->memberNumber,
            $record->objectNumber,
        );
    }

    /** @param array<string, true> $seenAssignments */
    private function assertNotDuplicated(array $seenAssignments, MemberObjectCsvRecord $record): void
    {
        if (!array_key_exists($this->assignmentKey($record), $seenAssignments)) {
            return;
        }

        throw CsvValueException::duplicateRecord($record->rowNumber, $record->memberNumber, $record->objectNumber);
    }

    private function assertNoOverlap(StorageSpace $storageSpace, MemberObjectCsvRecord $record): void
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

        throw CsvValueException::overlappingRental($record->rowNumber, $record->memberNumber, $record->objectNumber);
    }

    private function assertMemberIsActive(Member $member, MemberObjectCsvRecord $record): void
    {
        if (!$record->isOpenEnded() || $member->stopped_at === null) {
            return;
        }

        throw CsvValueException::openEndForStoppedMember($record->rowNumber, $record->memberNumber, 'rental');
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

    private function assignmentKey(MemberObjectCsvRecord $record): string
    {
        return sprintf('%s|%s', $record->memberNumber, $record->objectNumber);
    }
}
