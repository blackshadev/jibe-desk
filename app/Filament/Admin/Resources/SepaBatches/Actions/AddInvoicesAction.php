<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Actions;

use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\MandateExpirationStatus;
use App\Domain\Members\PaymentMethod;
use App\Filament\Admin\Resources\SepaBatches\Helpers\OnOpenSepaBatch;
use App\Models\Invoice;
use App\Models\SepaBatch;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Webmozart\Assert\Assert;

final class AddInvoicesAction
{
    public static function make(): Action
    {
        return Action::make('addInvoices')
            ->label(__('labels.add_invoices'))
            ->icon('heroicon-m-plus')
            ->schema([
                Select::make('invoice_ids')
                    ->label(__('labels.invoices'))
                    ->multiple()
                    ->searchable()
                    ->options(
                        static fn () => Invoice::query()
                            ->with('member.currentPaymentInformation')
                            ->whereNull('sepa_batch_id')
                            ->where('status', InvoiceStatus::Open)
                            ->orderBy('invoice_number')
                            ->get()
                            ->filter(static function (Invoice $invoice): bool {
                                $member = $invoice->member;

                                return (
                                    $member === null
                                    || $member->payment_method !== PaymentMethod::Transfer
                                    || $member->currentPaymentInformation?->expiration_status === MandateExpirationStatus::Active
                                );
                            })
                            ->mapWithKeys(static function (Invoice $invoice): array {
                                $label = $invoice->invoice_number . ' — ' . $invoice->recipient_name;
                                $paymentMethod = $invoice->member?->payment_method;

                                if ($paymentMethod !== null) {
                                    $label .= ' (' . __('labels.payment_methods.' . $paymentMethod->value) . ')';
                                }

                                return [$invoice->id => $label];
                            }),
                    ),
            ])
            ->requiresConfirmation()
            ->action(static function (array $data, RelationManager $livewire): void {
                $batch = $livewire->getOwnerRecord();
                Assert::isInstanceOf($batch, SepaBatch::class);

                Invoice::query()
                    ->whereIn('id', $data['invoice_ids'])
                    ->update(['sepa_batch_id' => $batch->id]);
            })
            ->successNotificationTitle(__('notifications.invoices_added_to_batch'))
            ->after(static fn (RelationManager $livewire) => $livewire->dispatch('refresh'))
            ->visible(static function (RelationManager $livewire): bool {
                $batch = $livewire->getOwnerRecord();

                return $batch instanceof SepaBatch && OnOpenSepaBatch::make($batch);
            });
    }
}
