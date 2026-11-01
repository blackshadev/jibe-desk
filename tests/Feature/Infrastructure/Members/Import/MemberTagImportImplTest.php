<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Members\Import;

use App\Domain\Members\Import\CsvImportException;
use App\Domain\Members\Import\MemberTagImportRequest;
use App\Infrastructure\Members\Import\MemberTagImportImpl;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\MemberObject;
use App\Models\MemberObjectType;
use Database\Seeders\CostCenterSeeder;
use Database\Seeders\MemberObjectTypeSeeder;
use Override;
use Tests\FeatureTestCase;
use Tests\Fixtures\MemberImport\CsvFixture;

final class MemberTagImportImplTest extends FeatureTestCase
{
    private const array HEADERS = ['lidnr', 'tagnummer', 'start_date', 'end_date'];

    private MemberTagImportImpl $subject;

    /** @var list<string> */
    private array $csvPaths = [];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = app(MemberTagImportImpl::class);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->csvPaths as $path) {
            if (!is_file($path)) {
                continue;
            }

            unlink($path);
        }

        parent::tearDown();
    }

    public function test_import_creates_member_objects_without_invoice_lines(): void
    {
        $this->seedTagObjectType();
        $anna = $this->member('1001');
        $bob = $this->member('1002');

        $report = $this->subject->import($this->request([
            ['1001', '012345678', '2026-01-01', ''],
            ['1002', '12345678',  '2026-02-01', '2026-06-30'],
        ]));

        static::assertSame(2, $report->importedTags);
        static::assertSame(2, MemberObject::query()->count());
        static::assertSame(0, InvoiceLine::query()->count());

        $tagObjectType = MemberObjectType::query()->where('name', 'Tag')->sole();
        $annaTag = MemberObject::query()->where('member_id', $anna->id)->sole();

        static::assertSame($tagObjectType->id, $annaTag->member_object_type_id);
        static::assertSame('012345678', $annaTag->name);
        static::assertSame('2026-01-01', $annaTag->start_date?->format('Y-m-d'));
        static::assertNull($annaTag->end_date);
        static::assertNull($annaTag->invoice_line_id);

        $bobTag = MemberObject::query()->where('member_id', $bob->id)->sole();

        static::assertSame('12345678', $bobTag->name);
        static::assertSame('2026-06-30', $bobTag->end_date?->format('Y-m-d'));
    }

    public function test_import_ignores_an_email_column(): void
    {
        $this->seedTagObjectType();
        $this->member('1001');

        $path = $this->csv(
            ['email', 'lidnr', 'tagnummer', 'start_date', 'end_date'],
            [['anna@example.com', '1001', '012345678', '2026-01-01', '']],
        );

        $report = $this->subject->import($this->requestForPath($path));

        static::assertSame(1, $report->importedTags);
    }

    public function test_import_dry_run_reports_without_writing(): void
    {
        $this->seedTagObjectType();
        $this->member('1001');

        $report = $this->subject->import($this->request([['1001', '012345678', '2026-01-01', '']], dryRun: true));

        static::assertSame(1, $report->importedTags);
        static::assertSame(0, MemberObject::query()->count());
    }

    public function test_import_fails_when_the_file_does_not_exist(): void
    {
        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('CSV file not found: /does/not/exist.csv');

        $this->subject->import(new MemberTagImportRequest('/does/not/exist.csv', false));
    }

    public function test_import_fails_when_a_header_is_missing(): void
    {
        $path = $this->csv(['lidnr', 'tagnummer', 'start_date'], [['1001', '012345678', '2026-01-01']]);

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('CSV is missing required columns: end_date');

        $this->subject->import($this->requestForPath($path));
    }

    public function test_import_fails_on_an_unreadable_date(): void
    {
        $this->seedTagObjectType();
        $this->member('1001');

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Row 2 (lidnr 1001): column "start_date", value "31-01-2026" is not a valid Y-m-d date.');

        $this->subject->import($this->request([['1001', '012345678', '31-01-2026', '']]));
    }

    public function test_import_fails_on_an_unknown_lidnr(): void
    {
        $this->seedTagObjectType();

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Row 2 (lidnr 9999): lidnr "9999" does not belong to a member.');

        $this->subject->import($this->request([['9999', '012345678', '2026-01-01', '']]));
    }

    public function test_import_fails_when_the_tag_object_type_is_missing(): void
    {
        $this->seed(CostCenterSeeder::class);
        $this->member('1001');

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Member object type "Tag" does not exist.');

        $this->subject->import($this->request([['1001', '012345678', '2026-01-01', '']]));
    }

    public function test_import_fails_on_a_duplicate_row(): void
    {
        $this->seedTagObjectType();
        $this->member('1001');

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Row 3 (lidnr 1001): "012345678" appears more than once for this member.');

        $this->subject->import($this->request([
            ['1001', '012345678', '2026-01-01', ''],
            ['1001', '012345678', '2026-02-01', ''],
        ]));
    }

    public function test_import_fails_when_the_tag_number_is_already_recorded(): void
    {
        $this->seedTagObjectType();
        $member = $this->member('1001');
        MemberObject::factory()->createQuietly([
            'member_id' => $member->id,
            'member_object_type_id' => MemberObjectType::query()->where('name', 'Tag')->sole()->id,
            'name' => '012345678',
            'start_date' => '2025-01-01',
            'end_date' => null,
        ]);

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Row 2 (lidnr 1001): tag "012345678" is already recorded.');

        $this->subject->import($this->request([['1001', '012345678', '2026-01-01', '']]));
    }

    public function test_import_fails_when_the_end_date_precedes_the_start_date(): void
    {
        $this->seedTagObjectType();
        $this->member('1001');

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Row 2 (lidnr 1001): end_date "2026-01-01" is before start_date "2026-06-01".');

        $this->subject->import($this->request([['1001', '012345678', '2026-06-01', '2026-01-01']]));
    }

    public function test_import_ends_an_open_ended_tag_for_a_stopped_member_on_the_member_end_date(): void
    {
        $this->seedTagObjectType();
        $this->member('1001', '2026-06-30');

        $report = $this->subject->import($this->request([['1001', '012345678', '2026-01-01', '']]));

        static::assertSame(1, $report->importedTags);

        $tag = MemberObject::query()->sole();

        static::assertSame('2026-01-01', $tag->start_date?->format('Y-m-d'));
        static::assertSame('2026-06-30', $tag->end_date?->format('Y-m-d'));
    }

    public function test_import_allows_a_closed_tag_for_a_stopped_member(): void
    {
        $this->seedTagObjectType();
        $this->member('1001', '2026-06-30');

        $report = $this->subject->import($this->request([['1001', '012345678', '2026-01-01', '2026-06-30']]));

        static::assertSame(1, $report->importedTags);
        static::assertSame(1, MemberObject::query()->count());
    }

    public function test_import_rolls_back_every_created_tag_when_a_later_row_fails(): void
    {
        $this->seedTagObjectType();
        $this->member('1001');

        try {
            $this->subject->import($this->request([
                ['1001', '012345678', '2026-01-01', ''],
                ['9999', '12345678',  '2026-01-01', ''],
            ]));
            static::fail('Expected exception');
        } catch (CsvImportException $exception) {
            static::assertSame(3, $exception->rowNumber);
            static::assertSame('9999', $exception->lidnr);
        }

        static::assertSame(0, MemberObject::query()->count());
    }

    private function seedTagObjectType(): void
    {
        $this->seed([CostCenterSeeder::class, MemberObjectTypeSeeder::class]);
    }

    private function member(string $lidnr, ?string $stoppedAt = null): Member
    {
        return Member::factory()->createQuietly([
            'lidnr' => $lidnr,
            'stopped_at' => $stoppedAt,
        ]);
    }

    /** @param list<list<string>> $rows */
    private function request(array $rows, bool $dryRun = false): MemberTagImportRequest
    {
        return $this->requestForPath($this->csv(self::HEADERS, $rows), $dryRun);
    }

    private function requestForPath(string $path, bool $dryRun = false): MemberTagImportRequest
    {
        return new MemberTagImportRequest(path: $path, dryRun: $dryRun);
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    private function csv(array $headers, array $rows): string
    {
        $path = CsvFixture::create($headers, $rows);
        $this->csvPaths[] = $path;

        return $path;
    }
}
