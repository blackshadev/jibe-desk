<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Actions;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Payment\Readiness\ExportConfirmation;
use App\Domain\Billing\Payment\Readiness\ExportNotReadyException;
use App\Domain\Billing\Payment\Readiness\ExportOverride;
use App\Domain\Billing\Payment\Readiness\ExportReadinessIssue;
use App\Domain\Billing\Payment\Readiness\ExportReadinessService;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchService;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Models\SepaBatch;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

final class ExportSepaAction
{
    public static function make(): Action
    {
        return Action::make('exportSepa')
            ->label(__('labels.export_sepa'))
            ->icon('heroicon-m-arrow-down-tray')
            ->color('info')
            ->visible(static fn (SepaBatch $record): bool => $record->status === SepaBatchStatus::Pending && (auth()->user()?->can('export', $record) ?? false))
            ->modalHeading(__('labels.export_readiness.export_heading'))
            ->schema(static function (SepaBatch $record, ExportReadinessService $readiness): array {
                $report = $readiness->assess(SepaBatchId::create($record->id));

                return [
                    ExportReadinessView::make($report),
                    CheckboxList::make('overridden_invoices')
                        ->label(__('labels.export_readiness.override_invoices'))
                        ->options(
                            static fn (): array => collect($report->overridableIssues())
                                ->mapWithKeys(static fn (ExportReadinessIssue $issue): array => [
                                    $issue->invoiceId?->value => sprintf(
                                        '%s — %s — %s',
                                        $issue->invoiceNumber,
                                        $issue->memberName,
                                        $issue->amount,
                                    ),
                                ])
                                ->all(),
                        )
                        ->visible(static fn (): bool => $report->overridableIssues() !== []),
                    Textarea::make('override_reason')
                        ->label(__('labels.export_readiness.override_reason'))
                        ->required(static fn (Get $get): bool => filled($get('overridden_invoices')))
                        ->visible(static fn (Get $get): bool => filled($get('overridden_invoices'))),
                ];
            })
            ->action(static function (
                SepaBatch $record,
                array $data,
                SepaBatchService $batchService,
                ExportReadinessService $readiness,
                Action $action,
            ): ?Response {
                $report = $readiness->assess(SepaBatchId::create($record->id));
                $overridableReasons = [];

                foreach ($report->overridableIssues() as $issue) {
                    if ($issue->invoiceId === null) {
                        continue;
                    }

                    $overridableReasons[$issue->invoiceId->value] = $issue->reason;
                }

                $overrides = [];

                foreach ($data['overridden_invoices'] ?? [] as $invoiceId) {
                    $reason = $overridableReasons[(int) $invoiceId] ?? null;

                    if ($reason === null) {
                        continue;
                    }

                    $overrides[] = new ExportOverride(
                        invoiceId: InvoiceId::create((int) $invoiceId),
                        reason: $reason,
                        note: (string) ($data['override_reason'] ?? ''),
                    );
                }

                $confirmation = new ExportConfirmation(overrides: $overrides);

                try {
                    $xml = $batchService->export(
                        SepaBatchId::create($record->id),
                        $confirmation,
                        is_int(auth()->id()) ? auth()->id() : null,
                    );
                } catch (ExportNotReadyException) {
                    $action->failure();

                    return null;
                }

                $filename = 'sepa-batch-' . $record->id . '-' . $record->invoice_date->format('Y-m-d');

                return self::downloadZip($filename, $xml->directDebit, $xml->creditTransfers);
            })
            ->after(static function (EditRecord $livewire): void {
                $livewire->dispatch('refresh');
            })
            ->failureNotificationTitle(__('notifications.sepa_export_blocked'));
    }

    private static function downloadZip(string $filename, string $directDebit, string $creditTransfers): Response
    {
        $zip = new ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), $filename) . '.zip';
        $zip->open($tmp, ZipArchive::CREATE);

        if ($directDebit !== '') {
            $zip->addFromString('sepa-direct-debits.xml', $directDebit);
        }

        if ($creditTransfers !== '') {
            $zip->addFromString('sepa-credit-transfers.xml', $creditTransfers);
        }

        $zip->close();

        return response()->download($tmp, $filename . '.zip', ['Content-Type' => 'application/zip']);
    }
}
