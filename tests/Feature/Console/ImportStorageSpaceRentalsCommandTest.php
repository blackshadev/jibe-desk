<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\BillableItemInstance;
use App\Models\Member;
use App\Models\StorageSpace;
use App\Models\StorageSpaceLocation;
use App\Models\StorageSpaceRental;
use Database\Seeders\CostCenterSeeder;
use Database\Seeders\StorageSpaceLocationSeeder;
use Override;
use Tests\FeatureTestCase;
use Tests\Fixtures\MemberImport\LegacyCsvFixture;

final class ImportStorageSpaceRentalsCommandTest extends FeatureTestCase
{
    /** @var list<string> */
    private array $csvPaths = [];

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

    public function test_it_imports_rentals_for_existing_members(): void
    {
        $this->seed([CostCenterSeeder::class, StorageSpaceLocationSeeder::class]);
        $location = StorageSpaceLocation::query()->where('name', 'Container 5')->sole();
        StorageSpace::factory()->createOneQuietly([
            'storage_space_location_id' => $location->id,
            'number' => '1',
        ]);
        $member = Member::factory()->createQuietly(['lidnr' => '1001']);
        $path = $this->csv([['1001', '5-01', '2026-03-01', '']]);

        $this
            ->artisan('app:import-storage-space-rentals', ['path' => $path])
            ->expectsOutputToContain('Import complete.')
            ->expectsOutputToContain('Imported rentals')
            ->expectsOutputToContain('Billing instances moved to the billing start')
            ->assertSuccessful();

        static::assertSame(1, StorageSpaceRental::query()->where('member_id', $member->id)->count());
        static::assertSame('2027-01-01', BillableItemInstance::query()->sole()->start_date->format('Y-m-d'));
    }

    public function test_the_billing_start_option_moves_the_instance_start(): void
    {
        $this->seed([CostCenterSeeder::class, StorageSpaceLocationSeeder::class]);
        $location = StorageSpaceLocation::query()->where('name', 'Container 5')->sole();
        StorageSpace::factory()->createOneQuietly([
            'storage_space_location_id' => $location->id,
            'number' => '1',
        ]);
        Member::factory()->createQuietly(['lidnr' => '1001']);
        $path = $this->csv([['1001', '5-01', '2026-03-01', '']]);

        $this
            ->artisan('app:import-storage-space-rentals', ['path' => $path, '--billing-start' => '2028-01-01'])
            ->assertSuccessful();

        static::assertSame('2028-01-01', BillableItemInstance::query()->sole()->start_date->format('Y-m-d'));
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->seed([CostCenterSeeder::class, StorageSpaceLocationSeeder::class]);
        $location = StorageSpaceLocation::query()->where('name', 'Container 5')->sole();
        StorageSpace::factory()->createOneQuietly([
            'storage_space_location_id' => $location->id,
            'number' => '1',
        ]);
        Member::factory()->createQuietly(['lidnr' => '1001']);
        $path = $this->csv([['1001', '5-01', '2026-03-01', '']]);

        $this
            ->artisan('app:import-storage-space-rentals', ['path' => $path, '--dry-run' => true])
            ->expectsOutputToContain('Dry run complete. The transaction was rolled back and no data was written.')
            ->assertSuccessful();

        static::assertSame(0, StorageSpaceRental::query()->count());
        static::assertSame(0, BillableItemInstance::query()->count());
    }

    public function test_it_fails_with_the_row_message(): void
    {
        $this->seed([CostCenterSeeder::class, StorageSpaceLocationSeeder::class]);
        $path = $this->csv([['9999', '5-01', '2026-03-01', '']]);

        $this
            ->artisan('app:import-storage-space-rentals', ['path' => $path])
            ->expectsOutputToContain('Row 2 (lidnr 9999): lidnr "9999" does not belong to a member.')
            ->assertFailed();
    }

    /** @param list<list<string>> $rows */
    private function csv(array $rows): string
    {
        $path = LegacyCsvFixture::create(['lidnr', 'objectnummer', 'start_date', 'end_date'], $rows);
        $this->csvPaths[] = $path;

        return $path;
    }
}
