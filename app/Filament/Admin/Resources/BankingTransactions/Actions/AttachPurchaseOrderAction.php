<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Filament\Admin\Resources\BankingTransactions\Helpers\GetTransaction;
use App\Filament\Admin\Resources\BankingTransactions\Helpers\IsOpen;
use App\Models\BankingTransaction;
use App\Models\BankStatement;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

final class AttachPurchaseOrderAction
{
    public static function make(): Action
    {
        return Action::make('attachPurchaseOrder')
            ->label(__('labels.attach_purchase_order'))
            ->icon(Heroicon::ShoppingCart)
            ->modalHeading(__('labels.attach_purchase_order'))
            ->visible(IsOpen::checkOwner(...))
            ->schema([
                Select::make('purchase_order_id')
                    ->label(__('labels.purchase_order'))
                    ->options(static function (RelationManager $livewire, BankingTransaction|BankStatement|null $record): Collection {
                        $model = GetTransaction::get($livewire, $record);

                        return PurchaseOrder::query()
                            ->openOrPending()
                            ->orderByRelevancy(-$model->amount, $model->banking_account_number)
                            ->get()
                            ->mapWithKeys(static fn (PurchaseOrder $po): array => [
                                $po->id => $po->displayName,
                            ]);
                    })
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->action(static function (array $data, RelationManager $livewire, BankingTransaction|BankStatement|null $record, BankTransactionService $service): void {
                $record = GetTransaction::get($livewire, $record);

                $service->attachPurchaseOrder(
                    BankTransactionId::create((int) $record->id),
                    PurchaseOrderId::create((int) $data['purchase_order_id']),
                );
            })
            ->successNotificationTitle(__('labels.attached'));
    }
}
