<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Member;
use App\Models\MemberObject;
use Database\Seeders\CostCenterSeeder;
use Database\Seeders\MemberObjectTypeSeeder;
use Override;
use Tests\FeatureTestCase;
use Tests\Fixtures\MemberImport\CsvFixture;

final class ImportMemberTagsCommandTest extends FeatureTestCase
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

    public function test_it_imports_tags_for_existing_members(): void
    {
        $this->seed([CostCenterSeeder::class, MemberObjectTypeSeeder::class]);
        $member = Member::factory()->createQuietly(['lidnr' => '1001']);
        $path = $this->csv([
            ['1001', '012345678', '2026-01-01', ''],
            ['1001', '12345678',  '2026-01-01', ''],
        ]);

        $this
            ->artisan('app:import-member-tags', ['path' => $path])
            ->expectsOutputToContain('Import complete.')
            ->expectsOutputToContain('Imported tags')
            ->assertSuccessful();

        static::assertSame(2, MemberObject::query()->where('member_id', $member->id)->count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->seed([CostCenterSeeder::class, MemberObjectTypeSeeder::class]);
        Member::factory()->createQuietly(['lidnr' => '1001']);
        $path = $this->csv([['1001', '012345678', '2026-01-01', '']]);

        $this
            ->artisan('app:import-member-tags', ['path' => $path, '--dry-run' => true])
            ->expectsOutputToContain('Dry run complete. The transaction was rolled back and no data was written.')
            ->assertSuccessful();

        static::assertSame(0, MemberObject::query()->count());
    }

    public function test_it_fails_with_the_row_message(): void
    {
        $this->seed([CostCenterSeeder::class, MemberObjectTypeSeeder::class]);
        $path = $this->csv([['9999', '012345678', '2026-01-01', '']]);

        $this
            ->artisan('app:import-member-tags', ['path' => $path])
            ->expectsOutputToContain('Row 2 (lidnr 9999): lidnr "9999" does not belong to a member.')
            ->assertFailed();

        static::assertSame(0, MemberObject::query()->count());
    }

    /** @param list<list<string>> $rows */
    private function csv(array $rows): string
    {
        $path = CsvFixture::create(['lidnr', 'tagnummer', 'start_date', 'end_date'], $rows);
        $this->csvPaths[] = $path;

        return $path;
    }
}
