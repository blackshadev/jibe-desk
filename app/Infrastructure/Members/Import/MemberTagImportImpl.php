<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\CsvValueException;
use App\Domain\Members\Import\MemberTagImport;
use App\Domain\Members\Import\MemberTagImportReport;
use App\Domain\Members\Import\MemberTagImportRequest;
use App\Models\Member;
use App\Models\MemberObject;
use App\Models\MemberObjectType;
use Illuminate\Support\Facades\DB;
use Override;
use Throwable;

final readonly class MemberTagImportImpl implements MemberTagImport
{
    private const string TAG_OBJECT_TYPE_NAME = 'Tag';

    private const array REQUIRED_HEADERS = ['lidnr', 'tagnummer', 'start_date', 'end_date'];

    public function __construct(
        private CsvFileReader $reader,
        private MemberObjectCsvRecordMapper $recordMapper,
    ) {}

    #[Override]
    public function import(MemberTagImportRequest $request): MemberTagImportReport
    {
        $spreadsheetRows = $this->reader->read($request->path, self::REQUIRED_HEADERS);
        $records = $this->recordMapper->map($spreadsheetRows, 'tagnummer');
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
    private function write(array $records): MemberTagImportReport
    {
        $memberObjectTypeId = $this->tagObjectTypeId();
        $membersByLidnr = $this->membersByLidnr($records);
        $recordedTagNumbers = $this->recordedTagNumbers($memberObjectTypeId, $records);

        $seenAssignedTags = [];
        $importedTags = 0;

        foreach ($records as $record) {
            $member = $membersByLidnr[$record->memberNumber] ?? throw CsvValueException::unknownLidnr($record->rowNumber, $record->memberNumber);

            $this->assertNotDuplicated($seenAssignedTags, $record);
            $this->assertTagNumberIsNew($recordedTagNumbers, $record);
            $this->assertMemberIsActive($member, $record);

            MemberObject::query()->createQuietly([
                'member_id' => $member->id,
                'member_object_type_id' => $memberObjectTypeId,
                'name' => $record->objectNumber,
                'start_date' => $record->startDate,
                'end_date' => $record->endDate,
            ]);

            $seenAssignedTags[$this->assignedTagKey($record)] = true;
            $recordedTagNumbers[$record->objectNumber] = true;
            $importedTags++;
        }

        return new MemberTagImportReport($importedTags);
    }

    private function tagObjectTypeId(): int
    {
        $memberObjectType = MemberObjectType::query()
            ->where('name', self::TAG_OBJECT_TYPE_NAME)
            ->first();

        return $memberObjectType->id ?? throw CsvValueException::missingMemberObjectType(self::TAG_OBJECT_TYPE_NAME);
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
     * @return array<string, true>
     */
    private function recordedTagNumbers(int $memberObjectTypeId, array $records): array
    {
        $tagNumbers = array_map(static fn (MemberObjectCsvRecord $record): string => $record->objectNumber, $records);

        $recordedNames = MemberObject::query()
            ->where('member_object_type_id', $memberObjectTypeId)
            ->whereIn('name', $tagNumbers)
            ->pluck('name');

        return array_fill_keys(
            $recordedNames->map(static fn (mixed $name): string => (string) $name)->all(),
            true,
        );
    }

    /** @param array<string, true> $seenAssignedTags */
    private function assertNotDuplicated(array $seenAssignedTags, MemberObjectCsvRecord $record): void
    {
        if (!array_key_exists($this->assignedTagKey($record), $seenAssignedTags)) {
            return;
        }

        throw CsvValueException::duplicateRecord($record->rowNumber, $record->memberNumber, $record->objectNumber);
    }

    /** @param array<string, true> $recordedTagNumbers */
    private function assertTagNumberIsNew(array $recordedTagNumbers, MemberObjectCsvRecord $record): void
    {
        if (!array_key_exists($record->objectNumber, $recordedTagNumbers)) {
            return;
        }

        throw CsvValueException::tagAlreadyRecorded($record->rowNumber, $record->memberNumber, $record->objectNumber);
    }

    private function assertMemberIsActive(Member $member, MemberObjectCsvRecord $record): void
    {
        if (!$record->isOpenEnded() || $member->stopped_at === null) {
            return;
        }

        throw CsvValueException::openEndForStoppedMember($record->rowNumber, $record->memberNumber, 'tag');
    }

    private function assignedTagKey(MemberObjectCsvRecord $record): string
    {
        return sprintf('%s|%s', $record->memberNumber, $record->objectNumber);
    }
}
