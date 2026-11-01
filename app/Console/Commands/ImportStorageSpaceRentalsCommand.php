<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Members\Import\CsvImportException;
use App\Domain\Members\Import\CsvValueException;
use App\Domain\Members\Import\StorageSpaceRentalImport;
use App\Domain\Members\Import\StorageSpaceRentalImportRequest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:import-storage-space-rentals {path} {--dry-run} {--billing-start=2027-01-01}')]
#[Description('Import legacy storage space rentals from a CSV export into existing members')]
final class ImportStorageSpaceRentalsCommand extends Command
{
    public function handle(StorageSpaceRentalImport $storageSpaceRentalImport): int
    {
        try {
            $request = new StorageSpaceRentalImportRequest(
                path: (string) $this->argument('path'),
                dryRun: (bool) $this->option('dry-run'),
                billingStart: $this->dateOption('billing-start'),
            );

            $report = $storageSpaceRentalImport->import($request);
        } catch (CsvImportException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($request->dryRun
            ? 'Dry run complete. The transaction was rolled back and no data was written.'
            : 'Import complete.');

        $this->table(['Result', 'Count'], [
            ['Imported rentals', $report->importedRentals],
            ['Billing instances moved to the billing start', $report->billingInstancesShifted],
        ]);

        return self::SUCCESS;
    }

    private function dateOption(string $option): CarbonImmutable
    {
        $value = (string) $this->option($option);

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            throw CsvValueException::invalidDateOption($option, $value);
        }
    }
}
