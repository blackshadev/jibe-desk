<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Members\Import\CsvImportException;
use App\Domain\Members\Import\StorageSpaceRentalImport;
use App\Domain\Members\Import\StorageSpaceRentalImportRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:import-storage-space-rentals {path} {--dry-run}')]
#[Description('Import legacy storage space rentals from a CSV export into existing members')]
final class ImportStorageSpaceRentalsCommand extends Command
{
    public function handle(StorageSpaceRentalImport $storageSpaceRentalImport): int
    {
        try {
            $request = new StorageSpaceRentalImportRequest(
                path: (string) $this->argument('path'),
                dryRun: (bool) $this->option('dry-run'),
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
        ]);

        return self::SUCCESS;
    }
}
