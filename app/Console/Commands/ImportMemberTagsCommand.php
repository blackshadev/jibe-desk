<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Members\Import\LegacyCsvImportException;
use App\Domain\Members\Import\MemberTagImport;
use App\Domain\Members\Import\MemberTagImportRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:import-member-tags {path} {--dry-run}')]
#[Description('Import legacy RFID tags from a CSV export into existing members')]
final class ImportMemberTagsCommand extends Command
{
    public function handle(MemberTagImport $memberTagImport): int
    {
        try {
            $request = new MemberTagImportRequest(
                path: (string) $this->argument('path'),
                dryRun: (bool) $this->option('dry-run'),
            );

            $report = $memberTagImport->import($request);
        } catch (LegacyCsvImportException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($request->dryRun
            ? 'Dry run complete. The transaction was rolled back and no data was written.'
            : 'Import complete.');

        $this->table(['Result', 'Count'], [
            ['Imported tags', $report->importedTags],
        ]);

        return self::SUCCESS;
    }
}
