<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\BankingTransactions\Helpers\IsOpen;
use App\Models\BankingTransaction;
use App\Models\CostCenter;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Intervention\Validation\Rules\Iban;

final class CreatePurchaseOrderFromTransactionAction
{
    public static function make(): Action
    {
        return Action::make('createPurchaseOrderFromTransaction')
            ->label(__('labels.create_purchase_order_from_transaction'))
            ->modalHeading(__('labels.create_purchase_order_from_transaction'))
            ->visible(IsOpen::checkOwner(...))
            ->schema(static function (RelationManager $livewire): array {
                /** @var BankingTransaction $record */
                $record = $livewire->getOwnerRecord();

                return [
                    DatePicker::make('date')
                        ->label(__('labels.date'))
                        ->native(false)
                        ->format('d-m-Y')
                        ->default($record->date)
                        ->required(),
                    TextInput::make('description')
                        ->label(__('labels.description'))
                        ->default($record->description)
                        ->columnSpanFull()
                        ->required(),
                    TextInput::make('creditor_iban')
                        ->label(__('labels.iban'))
                        ->default($record->banking_account_number)
                        ->rule(new Iban()),
                    TextInput::make('creditor_name')
                        ->label(__('labels.creditor_name')),
                    TextInput::make('line_price')
                        ->label(__('labels.price'))
                        ->prefix('€')
                        ->default(-$record->unmatched_amount)
                        ->live(true)
                        ->afterStateUpdated(static function (?float $state, Get $get, Set $set): void {
                            if ($state === null) {
                                return;
                            }

                            $set('line_price_vat', round($state * 0.21, 2));
                        })
                        ->required(),
                    TextInput::make('line_price_vat')
                        ->label(__('labels.price_vat'))
                        ->prefix('€')
                        ->default(round(-$record->amount * 0.21, 2))
                        ->required(),
                    FileUpload::make('image_path')
                        ->label(__('labels.image'))
                        ->image()
                        ->imagePreviewHeight('250')
                        ->directory('purchase-orders')
                        ->disk('local')
                        ->visibility('private')
                        ->previewable()
                        ->columnSpanFull(),
                    Select::make('cost_center_id')
                        ->label(__('labels.cost_center'))
                        ->options(static fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),
                ];
            })
            ->action(static function (array $data, RelationManager $livewire, BankTransactionService $bankingTransaction): void {
                /** @var BankingTransaction $record */
                $record = $livewire->getOwnerRecord();

                $po = PurchaseOrder::create([
                    'date' => $data['date'],
                    'description' => $data['description'],
                    'creditor_iban' => $data['creditor_iban'] ?? null,
                    'creditor_name' => $data['creditor_name'] ?? null,
                    'status' => PurchaseOrderStatus::Open,
                ]);

                $po->lines()->create([
                    'description' => $data['description'],
                    'price' => $data['line_price'],
                    'price_vat' => $data['line_price_vat'],
                    'cost_center_id' => $data['cost_center_id'],
                ]);

                $bankingTransaction->attachPurchaseOrder(
                    BankTransactionId::create($record->id),
                    PurchaseOrderId::create($po->id),
                );

                $livewire->dispatch('refresh');
            })
            ->successNotificationTitle(__('notifications.purchase_order_created_and_attached'));
    }
}
