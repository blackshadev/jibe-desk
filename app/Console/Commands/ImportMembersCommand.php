<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Members\Import\MemberImport;
use App\Domain\Members\Import\MemberImportEmailMismatch;
use App\Domain\Members\Import\MemberImportException;
use App\Domain\Members\Import\MemberImportReport;
use App\Domain\Members\Import\MemberImportReportRow;
use App\Domain\Members\Import\MemberImportRequest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:import-members {path} {--dry-run} {--billing-start=2027-01-01} {--last-collected=2026-12-31}')]
#[Description('Import the legacy member export into an empty database')]
final class ImportMembersCommand extends Command
{
    public function handle(MemberImport $memberImport): int
    {
        try {
            $request = new MemberImportRequest(
                path: (string) $this->argument('path'),
                dryRun: (bool) $this->option('dry-run'),
                billingStart: $this->dateOption('billing-start'),
                lastCollected: $this->dateOption('last-collected'),
            );

            $report = $memberImport->import($request);
        } catch (MemberImportException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $message = $request->dryRun
            ? 'Dry run complete. The transaction was rolled back and no data was written.'
            : 'Import complete.';
        $this->info($message);

        $this->renderReport($report);

        return self::SUCCESS;
    }

    private function dateOption(string $option): CarbonImmutable
    {
        $value = (string) $this->option($option);

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            throw MemberImportException::invalidDateOption($option, $value);
        }
    }

    private function renderReport(MemberImportReport $report): void
    {
        $this->table(['Result', 'Count'], [
            ['Imported members', $report->importedMembers],
            ['Stopped members', $report->stoppedMembers],
            ['Households created', $report->householdsCreated],
            ['Mandates created', $report->mandatesCreated],
            ['Billing instances created', $report->billingInstancesCreated],
            ['Erelid membership created', $report->erelidMembershipCreated ? 'yes' : 'no'],
        ]);

        $this->renderRows('Leavers', $report->leavers);
        $this->renderRows('RTC members', $report->rtcMembers);
        $this->renderRows('Container members', $report->containerMembers);
        $this->renderRows('Rows without Soortlid', $report->rowsWithoutMembershipType);
        $this->renderRows('Rows defaulted to Windsurfer', $report->rowsDefaultedToWindsurfer);
        $this->renderEmailMismatches($report->emailMismatches);
    }

    /** @param list<MemberImportReportRow> $rows */
    private function renderRows(string $title, array $rows): void
    {
        $this->newLine();
        $this->info(sprintf('%s (%d)', $title, count($rows)));

        if ($rows === []) {
            $this->line('None.');

            return;
        }

        $this->table(
            ['Lidnr', 'Name', 'Email'],
            array_map(
                static fn (MemberImportReportRow $row): array => [$row->lidnr, $row->name, $row->email],
                $rows,
            ),
        );
    }

    /** @param list<MemberImportEmailMismatch> $mismatches */
    private function renderEmailMismatches(array $mismatches): void
    {
        $this->newLine();
        $this->info(sprintf('Email mismatches (%d)', count($mismatches)));

        if ($mismatches === []) {
            $this->line('None.');

            return;
        }

        $this->table(
            ['Lidnr', 'Name', 'E-mail privé', 'Contact.email'],
            array_map(
                static fn (MemberImportEmailMismatch $mismatch): array => [
                    $mismatch->lidnr,
                    $mismatch->name,
                    $mismatch->privateEmail,
                    $mismatch->contactEmail,
                ],
                $mismatches,
            ),
        );
    }
}
