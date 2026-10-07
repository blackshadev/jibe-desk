<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InvoiceBatches\Actions;

use App\Domain\Invoices\InvoiceBatch;
use App\Domain\Invoices\InvoiceBatchGenerator;
use App\Filament\Admin\Resources\InvoiceBatches\InvoiceBatchResource;
use App\Models\InvoiceBatch as InvoiceBatchModel;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

final class GenerateInvoiceBatchAction
{
    public static function make(): Action
    {
        return Action::make('generate_invoice_batch')
            ->label(__('labels.generate_invoice_batch'))
            ->icon(Heroicon::Bolt)
            ->visible(static fn (): bool => auth()->user()?->can('create', InvoiceBatchModel::class) ?? false)
            ->schema([
                DatePicker::make('invoice_date')
                    ->label(__('labels.invoice_date'))
                    ->native(false)
                    ->format('d-m-Y')
                    ->default(now()->format('d-m-Y'))
                    ->required(),
                DatePicker::make('sepa_transfer_date')
                    ->label(__('labels.sepa_transfer_date'))
                    ->native(false)
                    ->format('d-m-Y')
                    ->default(now()->addDays(14)->format('d-m-Y'))
                    ->afterOrEqual('invoice_date')
                    ->required(),
            ])
            ->action(static function (ListRecords $livewire, array $data, InvoiceBatchGenerator $generator): void {
                $batchId = $generator->generate(new InvoiceBatch(
                    invoiceDate: CarbonImmutable::parse($data['invoice_date']),
                    sepaTransferDate: CarbonImmutable::parse($data['sepa_transfer_date']),
                ));

                $livewire->redirect(InvoiceBatchResource::getUrl('edit', ['record' => $batchId->value]));
            })
            ->successNotificationTitle(__('notifications.batch_generated'));
    }
}
