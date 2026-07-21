<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\RelationManagers;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Filament\Admin\Resources\BankingTransactions\Actions\AttachPurchaseOrderAction;
use App\Filament\Admin\Resources\BankingTransactions\Actions\CreatePurchaseOrderFromTransactionAction;
use App\Filament\Admin\Resources\BankingTransactions\Helpers\IsOpen;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use App\Models\BankingTransaction;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Override;

final class PurchaseOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseOrders';

    protected static ?string $relatedResource = PurchaseOrderResource::class;

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label(__('labels.description')),
                TextColumn::make('date')
                    ->label(__('labels.date'))
                    ->date()
                    ->sortable(),
            ])
            ->filters([])
            ->recordUrl(ViewOrEdit::route(PurchaseOrderResource::class))
            ->headerActions(
                [
                    AttachPurchaseOrderAction::make(),
                    CreatePurchaseOrderFromTransactionAction::make(),
                ],
            )
            ->recordActions(
                [
                    Action::make('detach')
                        ->label(__('labels.detach'))
                        ->color('danger')
                        ->icon('heroicon-o-x-mark')
                        ->requiresConfirmation()
                        ->visible(IsOpen::checkOwner(...))
                        ->action(function (PurchaseOrder $record, BankTransactionRepository $repository): void {
                            /** @var BankingTransaction $model */
                            $model = $this->getOwnerRecord();
                            $repository->detachPurchaseOrder(
                                BankTransactionId::create($model->id),
                                PurchaseOrderId::create($record->id),
                            );
                        })
                        ->successNotificationTitle(__('labels.detached')),
                ],
            );
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.purchase_order'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.purchase_orders'));
    }
}
