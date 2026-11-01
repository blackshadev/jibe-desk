<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImport;
use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportRequest;
use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportValueException;
use App\Models\CostCenter;
use App\Models\CostCenterGroup;
use Illuminate\Support\Facades\DB;
use Override;
use Tests\FeatureTestCase;
use Tests\Fixtures\CostCenterImport\CostCenterImportFixture;

final class CostCenterImportImplTest extends FeatureTestCase
{
    private CostCenterImport $subject;

    /** @var list<string> */
    private array $paths = [];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = app(CostCenterImport::class);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            unlink($path);
        }

        parent::tearDown();
    }

    public function test_it_imports_the_accounts_and_the_hierarchy(): void
    {
        CostCenterGroup::query()->delete();

        $dataset = CostCenterImportFixture::largeDataset();
        $report = $this->subject->import($this->request($dataset));

        static::assertSame(197, $report->costCentersInFile);
        static::assertSame(31, $report->groupsInFile);
        static::assertSame(197, $report->costCentersCreated);
        static::assertSame(31, $report->groupsCreated);
        static::assertSame(197, CostCenter::query()->count());
        static::assertSame(31, CostCenterGroup::query()->count());

        $verdichting = CostCenterGroup::query()->where('number', '1.10')->sole();
        static::assertSame('Verdichting 10', $verdichting->name);
        static::assertSame('10', $verdichting->external_number);
        static::assertSame('1', $verdichting->parent?->number);

        $account = CostCenter::query()->where('number', '1')->sole();
        static::assertSame('Rekening 1', $account->title);
        static::assertSame('1.10', $account->costCenterGroup->number);
        static::assertNull($account->description);
    }

    public function test_a_second_import_changes_nothing(): void
    {
        CostCenterGroup::query()->delete();

        $dataset = CostCenterImportFixture::largeDataset();
        $this->subject->import($this->request($dataset));

        DB::table('cost_centers')->update(['updated_at' => '2020-01-01 00:00:00']);
        DB::table('cost_center_groups')->update(['updated_at' => '2020-01-01 00:00:00']);

        $report = $this->subject->import($this->request($dataset));

        static::assertSame(0, $report->costCentersCreated);
        static::assertSame(0, $report->costCentersUpdated);
        static::assertSame(0, $report->groupsCreated);
        static::assertSame(0, $report->groupsUpdated);
        static::assertSame(0, DB::table('cost_centers')->where('updated_at', '!=', '2020-01-01 00:00:00')->count());
        static::assertSame(0, DB::table('cost_center_groups')->where('updated_at', '!=', '2020-01-01 00:00:00')->count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        CostCenterGroup::query()->delete();

        $dataset = CostCenterImportFixture::largeDataset();
        $report = $this->subject->import($this->request($dataset, dryRun: true));

        static::assertSame(197, $report->costCentersCreated);
        static::assertSame(31, $report->groupsCreated);
        static::assertSame(0, CostCenter::query()->count());
        static::assertSame(0, CostCenterGroup::query()->count());
    }

    public function test_validate_only_writes_nothing(): void
    {
        CostCenterGroup::query()->delete();

        $dataset = CostCenterImportFixture::largeDataset();
        $report = $this->subject->import($this->request($dataset, validateOnly: true));

        static::assertSame(197, $report->costCentersInFile);
        static::assertSame(31, $report->groupsInFile);
        static::assertSame(0, $report->costCentersCreated);
        static::assertSame(0, $report->groupsCreated);
        static::assertSame(0, CostCenter::query()->count());
        static::assertSame(0, CostCenterGroup::query()->count());
    }

    public function test_it_reports_rows_that_are_absent_from_the_files_without_deleting_them(): void
    {
        CostCenterGroup::query()->delete();

        $orphanGroup = CostCenterGroup::query()->create([
            'number' => '999',
            'external_number' => '999',
            'name' => 'Handmatig',
        ]);
        $otherOrphanGroup = CostCenterGroup::query()->create([
            'number' => '998',
            'external_number' => '998',
            'name' => 'Ook handmatig',
        ]);
        $orphanCostCenter = CostCenter::query()->create([
            'number' => '9999',
            'title' => 'Handmatige kostenplaats',
            'cost_center_group_id' => $orphanGroup->id,
        ]);
        $otherOrphanCostCenter = CostCenter::query()->create([
            'number' => '9998',
            'title' => 'Ook een handmatige kostenplaats',
            'cost_center_group_id' => $otherOrphanGroup->id,
        ]);

        $report = $this->subject->import($this->request(CostCenterImportFixture::smallDataset()));

        static::assertSame(['9998', '9999'], $report->costCentersAbsentFromFiles);
        static::assertSame(['998', '999'], $report->groupsAbsentFromFiles);
        static::assertTrue($orphanCostCenter->exists);
        static::assertTrue($otherOrphanCostCenter->exists);
        static::assertTrue($orphanGroup->exists);
        static::assertTrue($otherOrphanGroup->exists);
    }

    public function test_it_fails_when_an_account_references_an_unknown_verdichting(): void
    {
        $dataset = CostCenterImportFixture::smallDataset();
        $dataset['grootboek'] = CostCenterImportFixture::grootboek([
            ['100', 'Materiaal', 'B', 'K', 'D', '', '77', 'Onbekend'],
        ]);

        try {
            $this->subject->import($this->request($dataset));
            static::fail('Expected the import to fail.');
        } catch (CostCenterImportValueException $exception) {
            static::assertStringContainsString('100', $exception->getMessage());
            static::assertStringContainsString('77', $exception->getMessage());
        }
    }

    public function test_it_fails_when_the_group_files_disagree_about_the_hierarchy(): void
    {
        $dataset = CostCenterImportFixture::smallDataset();
        $dataset['verdichtingen'] = CostCenterImportFixture::verdichtingen([
            ['10', 'materiaal', '20', 'Verkeerde naam'],
        ]);

        try {
            $this->subject->import($this->request($dataset));
            static::fail('Expected the import to fail.');
        } catch (CostCenterImportValueException $exception) {
            static::assertStringContainsString('10', $exception->getMessage());
            static::assertStringContainsString('disagree', $exception->getMessage());
        }
    }

    public function test_it_fails_when_a_verdichting_references_an_unknown_hoofdverdichting(): void
    {
        $dataset = CostCenterImportFixture::smallDataset();
        $dataset['verdichtingen'] = CostCenterImportFixture::verdichtingen([
            ['10', 'materiaal', '99', 'Kosten'],
        ]);

        try {
            $this->subject->import($this->request($dataset));
            static::fail('Expected the import to fail.');
        } catch (CostCenterImportValueException $exception) {
            static::assertStringContainsString('10', $exception->getMessage());
        }
    }

    /**
     * @param array{grootboek: string, verdichtingen: string, hoofdverdichtingen: string} $dataset
     */
    private function request(array $dataset, bool $dryRun = false, bool $validateOnly = false): CostCenterImportRequest
    {
        foreach (['grootboek', 'verdichtingen', 'hoofdverdichtingen'] as $key) {
            $this->paths[] = $dataset[$key];
        }

        return new CostCenterImportRequest(
            grootboekPath: $dataset['grootboek'],
            verdichtingenPath: $dataset['verdichtingen'],
            hoofdverdichtingenPath: $dataset['hoofdverdichtingen'],
            dryRun: $dryRun,
            validateOnly: $validateOnly,
        );
    }
}
