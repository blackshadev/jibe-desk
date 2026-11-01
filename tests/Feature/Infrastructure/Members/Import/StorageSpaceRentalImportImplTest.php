<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Members\Import;

use App\Domain\Members\Import\CsvImportException;
use App\Domain\Members\Import\StorageSpaceRentalImportRequest;
use App\Infrastructure\Members\Import\StorageSpaceRentalImportImpl;
use App\Models\BillableItemInstance;
use App\Models\Member;
use App\Models\StorageSpace;
use App\Models\StorageSpaceLocation;
use App\Models\StorageSpaceRental;
use Database\Seeders\CostCenterSeeder;
use Database\Seeders\StorageSpaceLocationSeeder;
use DateTimeImmutable;
use Override;
use Tests\FeatureTestCase;
use Tests\Fixtures\MemberImport\CsvFixture;

final class StorageSpaceRentalImportImplTest extends FeatureTestCase
{
    private const array HEADERS = ['lidnr', 'objectnummer', 'start_date', 'end_date'];

    private StorageSpaceRentalImportImpl $subject;

    /** @var list<string> */
    private array $csvPaths = [];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = app(StorageSpaceRentalImportImpl::class);
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

    public function test_import_creates_rentals_and_shifts_the_open_ones_to_the_billing_start(): void
    {
        $this->seedLocations();
        $containerFiveOne = $this->storageSpace('Container 5', '1');
        $containerFiveTwo = $this->storageSpace('Container 5', '2');
        $containerFourSeven = $this->storageSpace('Container 4', '7');
        $anna = $this->member('1001');
        $bob = $this->member('1002');

        $report = $this->subject->import($this->request([
            ['1001', '5-01', '2026-03-01', ''],
            ['1001', '5-02', '2026-03-01', '2026-08-31'],
            ['1002', '4-07', '2026-03-01', '2027-06-30'],
        ]));

        static::assertSame(3, $report->importedRentals);
        static::assertSame(2, $report->billingInstancesShifted);
        static::assertSame(3, StorageSpaceRental::query()->count());

        $openRental = StorageSpaceRental::query()
            ->where('storage_space_id', $containerFiveOne->id)
            ->sole();

        static::assertSame($anna->id, $openRental->member_id);
        static::assertSame('2026-03-01', $openRental->start_date?->format('Y-m-d'));
        static::assertNull($openRental->end_date);
        static::assertNotNull($openRental->billable_item_instance_id);

        $openInstance = BillableItemInstance::query()->findOrFail($openRental->billable_item_instance_id);
        static::assertSame('2027-01-01', $openInstance->start_date->format('Y-m-d'));
        static::assertNull($openInstance->end_date);

        $endedRental = StorageSpaceRental::query()
            ->where('storage_space_id', $containerFiveTwo->id)
            ->sole();
        $endedInstance = BillableItemInstance::query()->findOrFail($endedRental->billable_item_instance_id);
        static::assertSame('2026-03-01', $endedInstance->start_date->format('Y-m-d'));
        static::assertSame('2026-08-31', $endedInstance->end_date?->format('Y-m-d'));

        $futureEndedRental = StorageSpaceRental::query()
            ->where('storage_space_id', $containerFourSeven->id)
            ->sole();
        $futureEndedInstance = BillableItemInstance::query()->findOrFail($futureEndedRental->billable_item_instance_id);
        static::assertSame('2027-01-01', $futureEndedInstance->start_date->format('Y-m-d'));
        static::assertSame('2027-06-30', $futureEndedInstance->end_date?->format('Y-m-d'));

        static::assertSame($bob->id, $futureEndedRental->member_id);
    }

    public function test_import_dry_run_reports_without_writing(): void
    {
        $this->seedLocations();
        $this->storageSpace('Container 5', '1');
        $this->member('1001');

        $report = $this->subject->import(
            $this->request([['1001', '5-01', '2026-03-01', '']], dryRun: true),
        );

        static::assertSame(1, $report->importedRentals);
        static::assertSame(1, $report->billingInstancesShifted);
        static::assertSame(0, StorageSpaceRental::query()->count());
        static::assertSame(0, BillableItemInstance::query()->count());
    }

    public function test_import_fails_when_the_object_number_is_malformed(): void
    {
        $this->seedLocations();
        $this->member('1001');

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Row 2 (lidnr 1001): object number "501" must look like 5-01.');

        $this->subject->import($this->request([['1001', '501', '2026-03-01', '']]));
    }

    public function test_import_fails_on_an_unknown_storage_location(): void
    {
        $this->seedLocations();
        $this->member('1001');

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Row 2 (lidnr 1001): storage location "Container 9" does not exist.');

        $this->subject->import($this->request([['1001', '9-01', '2026-03-01', '']]));
    }

    public function test_import_fails_on_an_unknown_storage_space(): void
    {
        $this->seedLocations();
        $this->storageSpace('Container 5', '1');
        $this->member('1001');

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Row 2 (lidnr 1001): storage space "5-99" does not exist.');

        $this->subject->import($this->request([['1001', '5-99', '2026-03-01', '']]));
    }

    public function test_import_fails_on_an_unknown_lidnr(): void
    {
        $this->seedLocations();
        $this->storageSpace('Container 5', '1');

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Row 2 (lidnr 9999): lidnr "9999" does not belong to a member.');

        $this->subject->import($this->request([['9999', '5-01', '2026-03-01', '']]));
    }

    public function test_import_fails_on_a_duplicate_row(): void
    {
        $this->seedLocations();
        $this->storageSpace('Container 5', '1');
        $this->member('1001');

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Row 3 (lidnr 1001): "5-01" appears more than once for this member.');

        $this->subject->import($this->request([
            ['1001', '5-01', '2026-03-01', ''],
            ['1001', '5-01', '2026-04-01', ''],
        ]));
    }

    public function test_import_fails_on_an_overlapping_rental(): void
    {
        $this->seedLocations();
        $storageSpace = $this->storageSpace('Container 5', '1');
        $member = $this->member('1001');

        StorageSpaceRental::factory()->createQuietly([
            'storage_space_id' => $storageSpace->id,
            'member_id' => $member->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Row 2 (lidnr 1001): storage space "5-01" is already rented in this period.');

        $this->subject->import($this->request([['1001', '5-01', '2026-03-01', '2026-08-31']]));
    }

    public function test_import_fails_on_an_overlap_created_by_an_earlier_row(): void
    {
        $this->seedLocations();
        $this->storageSpace('Container 5', '1');
        $this->member('1001');
        $this->member('1002');

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Row 3 (lidnr 1002): storage space "5-01" is already rented in this period.');

        $this->subject->import($this->request([
            ['1001', '5-01', '2026-01-01', '2026-12-31'],
            ['1002', '5-01', '2026-03-01', '2026-08-31'],
        ]));
    }

    public function test_import_fails_when_the_end_date_precedes_the_start_date(): void
    {
        $this->seedLocations();
        $this->storageSpace('Container 5', '1');
        $this->member('1001');

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Row 2 (lidnr 1001): end_date "2026-01-01" is before start_date "2026-06-01".');

        $this->subject->import($this->request([['1001', '5-01', '2026-06-01', '2026-01-01']]));
    }

    public function test_import_fails_on_an_open_ended_rental_for_a_stopped_member(): void
    {
        $this->seedLocations();
        $this->storageSpace('Container 5', '1');
        $this->member('1001', '2026-06-30');

        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('Row 2 (lidnr 1001): an open-ended rental is not allowed because the member has stopped.');

        $this->subject->import($this->request([['1001', '5-01', '2026-03-01', '']]));
    }

    public function test_import_rolls_back_every_created_rental_when_a_later_row_fails(): void
    {
        $this->seedLocations();
        $this->storageSpace('Container 5', '1');
        $this->member('1001');

        try {
            $this->subject->import($this->request([
                ['1001', '5-01', '2026-03-01', ''],
                ['9999', '9-01', '2026-03-01', ''],
            ]));
            static::fail('Expected exception');
        } catch (CsvImportException $exception) {
            static::assertSame(3, $exception->rowNumber);
        }

        static::assertSame(0, StorageSpaceRental::query()->count());
        static::assertSame(0, BillableItemInstance::query()->count());
    }

    public function test_import_shifts_billing_on_a_custom_billing_start(): void
    {
        $this->seedLocations();
        $this->storageSpace('Container 5', '1');
        $this->member('1001');

        $this->subject->import($this->request(
            [['1001', '5-01', '2026-03-01', '']],
            billingStart: '2028-01-01',
        ));

        $instance = BillableItemInstance::query()->sole();

        static::assertSame('2028-01-01', $instance->start_date->format('Y-m-d'));
    }

    private function seedLocations(): void
    {
        $this->seed([CostCenterSeeder::class, StorageSpaceLocationSeeder::class]);
    }

    private function storageSpace(string $locationName, string $number): StorageSpace
    {
        $location = StorageSpaceLocation::query()->where('name', $locationName)->sole();

        return StorageSpace::factory()->createOneQuietly([
            'storage_space_location_id' => $location->id,
            'number' => $number,
        ]);
    }

    private function member(string $lidnr, ?string $stoppedAt = null): Member
    {
        return Member::factory()->createQuietly([
            'lidnr' => $lidnr,
            'stopped_at' => $stoppedAt,
        ]);
    }

    /** @param list<list<string>> $rows */
    private function request(array $rows, bool $dryRun = false, string $billingStart = '2027-01-01'): StorageSpaceRentalImportRequest
    {
        return new StorageSpaceRentalImportRequest(
            path: $this->csv($rows),
            dryRun: $dryRun,
            billingStart: new DateTimeImmutable($billingStart),
        );
    }

    /** @param list<list<string>> $rows */
    private function csv(array $rows): string
    {
        $path = CsvFixture::create(self::HEADERS, $rows);
        $this->csvPaths[] = $path;

        return $path;
    }
}
