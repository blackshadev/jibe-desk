<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\RelationManagers;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\Invoices\InvoiceId;
use App\Filament\Admin\Resources\BankingTransactions\Actions\AttachInvoiceAction;
use App\Filament\Admin\Resources\BankingTransactions\Actions\CreateInvoiceFromTransactionAction;
use App\Filament\Admin\Resources\BankingTransactions\Helpers\IsOpen;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use App\Models\BankingTransaction;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Override;

final class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $relatedResource = InvoiceResource::class;

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label(__('labels.invoice_number')),
                TextColumn::make('date')
                    ->label(__('labels.date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('total')
                    ->label(__('labels.total'))
                    ->alignEnd(),
            ])
            ->recordUrl(ViewOrEdit::route(InvoiceResource::class))
            ->headerActions([
                AttachInvoiceAction::make(),
                CreateInvoiceFromTransactionAction::make(),
            ])
            ->filters([])
            ->recordActions(
                [
                    Action::make('detach')
                        ->label(__('labels.detach'))
                        ->color('danger')
                        ->icon('heroicon-o-x-mark')
                        ->requiresConfirmation()
                        ->visible(IsOpen::checkOwner(...))
                        ->action(function (Invoice $record, BankTransactionRepository $repository): void {
                            /** @var BankingTransaction $model */
                            $model = $this->getOwnerRecord();
                            $repository->detachInvoice(
                                BankTransactionId::create($model->id),
                                InvoiceId::create($record->id),
                            );
                        })
                        ->successNotificationTitle(__('labels.detached')),
                ],
            );
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.invoice'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.invoices'));
    }
}
