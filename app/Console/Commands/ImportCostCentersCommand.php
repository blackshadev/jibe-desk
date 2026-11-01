<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImport;
use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportException;
use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportReport;
use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:import-cost-centers {grootboek} {verdichtingen} {hoofdverdichtingen} {--dry-run} {--validate-only}')]
#[Description('Import the e-Captain grootboek and its verdichting hierarchy into cost centers')]
final class ImportCostCentersCommand extends Command
{
    public function handle(CostCenterImport $costCenterImport): int
    {
        $request = new CostCenterImportRequest(
            grootboekPath: (string) $this->argument('grootboek'),
            verdichtingenPath: (string) $this->argument('verdichtingen'),
            hoofdverdichtingenPath: (string) $this->argument('hoofdverdichtingen'),
            dryRun: (bool) $this->option('dry-run'),
            validateOnly: (bool) $this->option('validate-only'),
        );

        try {
            $report = $costCenterImport->import($request);
        } catch (CostCenterImportException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($this->completionMessage($request));
        $this->renderReport($report);

        return self::SUCCESS;
    }

    private function completionMessage(CostCenterImportRequest $request): string
    {
        if ($request->validateOnly) {
            return 'Validation complete. The files are valid and nothing was written.';
        }

        if ($request->dryRun) {
            return 'Dry run complete. The transaction was rolled back and no data was written.';
        }

        return 'Import complete.';
    }

    private function renderReport(CostCenterImportReport $report): void
    {
        $this->table(['Result', 'Count'], [
            ['Cost centers in files', $report->costCentersInFile],
            ['Kostengroepen in files', $report->groupsInFile],
            ['Cost centers created', $report->costCentersCreated],
            ['Cost centers updated', $report->costCentersUpdated],
            ['Kostengroepen created', $report->groupsCreated],
            ['Kostengroepen updated', $report->groupsUpdated],
        ]);

        $this->renderNumbers(
            'Cost centers in the database but not in the grootboek file',
            $report->costCentersAbsentFromFiles,
        );
        $this->renderNumbers(
            'Kostengroepen in the database but not in the group files',
            $report->groupsAbsentFromFiles,
        );
    }

    /** @param list<string> $numbers */
    private function renderNumbers(string $title, array $numbers): void
    {
        $this->newLine();
        $this->info(sprintf('%s (%d)', $title, count($numbers)));

        if ($numbers === []) {
            $this->line('None.');

            return;
        }

        $this->table(['Number'], array_map(static fn (string $number): array => [$number], $numbers));
    }
}
