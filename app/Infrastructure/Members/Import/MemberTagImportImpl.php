<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\LegacyCsvImportException;
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
        private LegacyCsvReader $reader,
        private LegacyCsvRecordMapper $recordMapper,
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

    /** @param list<LegacyCsvRecord> $records */
    private function write(array $records): MemberTagImportReport
    {
        $memberObjectTypeId = $this->tagObjectTypeId();
        $membersByLidnr = $this->membersByLidnr($records);
        $recordedTagNumbers = $this->recordedTagNumbers($memberObjectTypeId, $records);

        $seenAssignedTags = [];
        $importedTags = 0;

        foreach ($records as $record) {
            $member = $membersByLidnr[$record->lidnr] ?? throw LegacyCsvImportException::unknownLidnr($record->rowNumber, $record->lidnr);

            $this->assertNotDuplicated($seenAssignedTags, $record);
            $this->assertTagNumberIsNew($recordedTagNumbers, $record);
            $this->assertMemberIsActive($member, $record);

            MemberObject::query()->createQuietly([
                'member_id' => $member->id,
                'member_object_type_id' => $memberObjectTypeId,
                'name' => $record->number,
                'start_date' => $record->startDate,
                'end_date' => $record->endDate,
            ]);

            $seenAssignedTags[$this->assignedTagKey($record)] = true;
            $recordedTagNumbers[$record->number] = true;
            $importedTags++;
        }

        return new MemberTagImportReport($importedTags);
    }

    private function tagObjectTypeId(): int
    {
        $memberObjectType = MemberObjectType::query()
            ->where('name', self::TAG_OBJECT_TYPE_NAME)
            ->first();

        return $memberObjectType->id ?? throw LegacyCsvImportException::missingMemberObjectType(self::TAG_OBJECT_TYPE_NAME);
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
     * @return array<string, true>
     */
    private function recordedTagNumbers(int $memberObjectTypeId, array $records): array
    {
        $tagNumbers = array_map(static fn (LegacyCsvRecord $record): string => $record->number, $records);

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
    private function assertNotDuplicated(array $seenAssignedTags, LegacyCsvRecord $record): void
    {
        if (!array_key_exists($this->assignedTagKey($record), $seenAssignedTags)) {
            return;
        }

        throw LegacyCsvImportException::duplicateRecord($record->rowNumber, $record->lidnr, $record->number);
    }

    /** @param array<string, true> $recordedTagNumbers */
    private function assertTagNumberIsNew(array $recordedTagNumbers, LegacyCsvRecord $record): void
    {
        if (!array_key_exists($record->number, $recordedTagNumbers)) {
            return;
        }

        throw LegacyCsvImportException::tagAlreadyRecorded($record->rowNumber, $record->lidnr, $record->number);
    }

    private function assertMemberIsActive(Member $member, LegacyCsvRecord $record): void
    {
        if (!$record->isOpenEnded() || $member->stopped_at === null) {
            return;
        }

        throw LegacyCsvImportException::openEndForStoppedMember($record->rowNumber, $record->lidnr, 'tag');
    }

    private function assignedTagKey(LegacyCsvRecord $record): string
    {
        return sprintf('%s|%s', $record->lidnr, $record->number);
    }
}
