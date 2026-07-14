<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Models\BankingTransaction;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;

final class AttachPurchaseOrderAction
{
    public static function make(): Action
    {
        return Action::make('attachPurchaseOrder')
            ->label(__('labels.attach_purchase_order'))
            ->modalHeading(__('labels.attach_purchase_order'))
            ->schema([
                Select::make('purchase_order_id')
                    ->label(__('labels.purchase_order'))
                    ->options(static fn () => PurchaseOrder::query()
                        ->orderBy('description')
                        ->get()
                        ->mapWithKeys(static fn (PurchaseOrder $po): array => [
                            $po->id => $po->description,
                        ]))
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->action(static function (array $data, RelationManager $livewire, BankTransactionRepository $repository): void {
                /** @var BankingTransaction $model */
                $model = $livewire->getOwnerRecord();
                $repository->attachPurchaseOrder(
                    BankTransactionId::create((int) $model->id),
                    PurchaseOrderId::create((int) $data['purchase_order_id']),
                );
            })
            ->successNotificationTitle(__('labels.attached'));
    }
}
