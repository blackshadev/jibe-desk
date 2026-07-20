<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\RelationManagers;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Filament\Admin\Resources\BankingTransactions\Actions\AttachBookkeepingRecordAction;
use App\Models\BankingTransaction;
use App\Models\BookkeepingRecord;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class BookkeepingRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookkeepingRecords';

    protected static ?string $title = 'Boekhouding mutaties';

    public function table(Table $table): Table
    {
        /** @var BankingTransaction $owner */
        $owner = $this->getOwnerRecord();

        return $table
            ->columns([
                TextColumn::make('year')
                    ->label(__('labels.book_year')),
                TextColumn::make('costCenter.title')
                    ->label(__('labels.cost_center')),
                TextColumn::make('description')
                    ->label(__('labels.description')),
                TextColumn::make('amount')
                    ->label(__('labels.price'))
                    ->money('EUR'),
            ])
            ->headerActions(
                [AttachBookkeepingRecordAction::make()],
            )
            ->recordActions(
                [
                    Action::make('detach')
                        ->label(__('labels.detach'))
                        ->color('danger')
                        ->icon('heroicon-o-x-mark')
                        ->requiresConfirmation()
                        ->visible(static function (RelationManager $livewire): bool {
                            /** @var BankingTransaction $ownerRecord */
                            $ownerRecord = $livewire->getOwnerRecord();

                            return !$ownerRecord->isCompleted();
                        })
                        ->action(function (BookkeepingRecord $record, BankTransactionRepository $repository): void {
                            /** @var BankingTransaction $model */
                            $model = $this->getOwnerRecord();
                            $repository->detachBookkeepingRecord(
                                BankTransactionId::create($model->id),
                                $record->id,
                            );
                        })
                        ->successNotificationTitle(__('labels.detached')),
                ],
            );
    }
}
