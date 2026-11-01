<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\CsvValueException;
use App\Domain\Members\Import\StorageSpaceRentalImport;
use App\Domain\Members\Import\StorageSpaceRentalImportReport;
use App\Domain\Members\Import\StorageSpaceRentalImportRequest;
use App\Models\Member;
use App\Models\StorageSpace;
use App\Models\StorageSpaceLocation;
use App\Models\StorageSpaceRental;
use DateTimeInterface;
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
            $report = $this->write($records);

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
    private function write(array $records): StorageSpaceRentalImportReport
    {
        $membersByLidnr = $this->membersByLidnr($records);
        $objectNumbers = $this->objectNumbers($records);
        $locationIdsByName = $this->locationIdsByName($objectNumbers);
        $storageSpacesByLocationId = $this->storageSpacesByLocationId($locationIdsByName);

        $seenAssignments = [];
        $importedRentals = 0;

        foreach ($records as $index => $record) {
            $member = $membersByLidnr[$record->memberNumber] ?? throw CsvValueException::unknownLidnr($record->rowNumber, $record->memberNumber);
            $objectNumber = $objectNumbers[$index];

            self::assertNotDuplicated($seenAssignments, $record);

            $storageSpace = $this->storageSpace($objectNumber, $record, $locationIdsByName, $storageSpacesByLocationId);
            $endDate = self::rentalEndDate($member, $record);

            self::assertNoOverlap($storageSpace, $record, $endDate);

            StorageSpaceRental::query()->create([
                'storage_space_id' => $storageSpace->id,
                'member_id' => $member->id,
                'start_date' => $record->startDate,
                'end_date' => $endDate,
            ]);

            $seenAssignments[self::assignmentKey($record)] = true;
            $importedRentals++;
        }

        return new StorageSpaceRentalImportReport(importedRentals: $importedRentals);
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
    private static function assertNotDuplicated(array $seenAssignments, MemberObjectCsvRecord $record): void
    {
        if (!array_key_exists(self::assignmentKey($record), $seenAssignments)) {
            return;
        }

        throw CsvValueException::duplicateRecord($record->rowNumber, $record->memberNumber, $record->objectNumber);
    }

    private static function assertNoOverlap(StorageSpace $storageSpace, MemberObjectCsvRecord $record, ?DateTimeInterface $endDate): void
    {
        $overlaps = StorageSpaceRental::query()
            ->overlapping(
                storageSpaceId: $storageSpace->id,
                startDate: $record->startDate,
                endDate: $endDate,
            )
            ->exists();

        if (!$overlaps) {
            return;
        }

        throw CsvValueException::overlappingRental($record->rowNumber, $record->memberNumber, $record->objectNumber);
    }

    private static function rentalEndDate(Member $member, MemberObjectCsvRecord $record): ?DateTimeInterface
    {
        if ($record->isOpenEnded()) {
            return $member->stopped_at;
        }

        if ($member->stopped_at === null && self::isLastDayOfDecember($record->endDate)) {
            return null;
        }

        return $record->endDate;
    }

    private static function isLastDayOfDecember(?DateTimeInterface $date): bool
    {
        return $date !== null
            && (int) $date->format('n') === 12
            && (int) $date->format('j') === 31;
    }

    private static function assignmentKey(MemberObjectCsvRecord $record): string
    {
        return sprintf('%s|%s', $record->memberNumber, $record->objectNumber);
    }
}
