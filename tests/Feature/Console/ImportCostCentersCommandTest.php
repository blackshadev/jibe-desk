<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\CostCenter;
use App\Models\CostCenterGroup;
use Override;
use Tests\FeatureTestCase;
use Tests\Fixtures\CostCenterImport\CostCenterImportFixture;

final class ImportCostCentersCommandTest extends FeatureTestCase
{
    /** @var list<string> */
    private array $paths = [];

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

    public function test_it_imports_the_three_files_and_renders_the_report(): void
    {
        CostCenterGroup::query()->delete();

        $dataset = CostCenterImportFixture::largeDataset();

        $this
            ->artisan('app:import-cost-centers', [
                'grootboek' => $this->track($dataset['grootboek']),
                'verdichtingen' => $this->track($dataset['verdichtingen']),
                'hoofdverdichtingen' => $this->track($dataset['hoofdverdichtingen']),
            ])
            ->expectsOutputToContain('Import complete.')
            ->expectsOutputToContain('Cost centers in files')
            ->expectsOutputToContain('Cost centers in the database but not in the grootboek file')
            ->expectsOutputToContain('Kostengroepen in the database but not in the group files')
            ->assertSuccessful();

        static::assertSame(197, CostCenter::query()->count());
        static::assertSame(31, CostCenterGroup::query()->count());
    }

    public function test_it_fails_with_a_non_zero_exit_code_when_the_files_disagree(): void
    {
        $dataset = CostCenterImportFixture::smallDataset();
        $dataset['verdichtingen'] = CostCenterImportFixture::verdichtingen([
            ['10', 'materiaal', '20', 'Verkeerde naam'],
        ]);

        $this
            ->artisan('app:import-cost-centers', [
                'grootboek' => $this->track($dataset['grootboek']),
                'verdichtingen' => $this->track($dataset['verdichtingen']),
                'hoofdverdichtingen' => $this->track($dataset['hoofdverdichtingen']),
            ])
            ->expectsOutputToContain('disagree about the hierarchy')
            ->assertFailed();

        static::assertSame(0, CostCenter::query()->count());
    }

    public function test_it_reports_a_dry_run_without_writing(): void
    {
        CostCenterGroup::query()->delete();

        $dataset = CostCenterImportFixture::smallDataset();

        $this
            ->artisan('app:import-cost-centers', [
                'grootboek' => $this->track($dataset['grootboek']),
                'verdichtingen' => $this->track($dataset['verdichtingen']),
                'hoofdverdichtingen' => $this->track($dataset['hoofdverdichtingen']),
                '--dry-run' => true,
            ])
            ->expectsOutputToContain('Dry run complete.')
            ->assertSuccessful();

        static::assertSame(0, CostCenter::query()->count());
        static::assertSame(0, CostCenterGroup::query()->count());
    }

    public function test_it_reports_validate_only_without_writing(): void
    {
        CostCenterGroup::query()->delete();

        $dataset = CostCenterImportFixture::smallDataset();

        $this
            ->artisan('app:import-cost-centers', [
                'grootboek' => $this->track($dataset['grootboek']),
                'verdichtingen' => $this->track($dataset['verdichtingen']),
                'hoofdverdichtingen' => $this->track($dataset['hoofdverdichtingen']),
                '--validate-only' => true,
            ])
            ->expectsOutputToContain('Validation complete.')
            ->assertSuccessful();

        static::assertSame(0, CostCenter::query()->count());
        static::assertSame(0, CostCenterGroup::query()->count());
    }

    private function track(string $path): string
    {
        $this->paths[] = $path;

        return $path;
    }
}
