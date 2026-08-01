<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\RelationManagers;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Filament\Admin\Resources\BankingTransactions\Actions\AttachBookkeepingRecordAction;
use App\Filament\Admin\Resources\BankingTransactions\Actions\CreateBookkeepingRecordFromTransactionAction;
use App\Filament\Admin\Resources\BankingTransactions\Helpers\IsOpen;
use App\Models\BankingTransaction;
use App\Models\BookkeepingRecord;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Override;

final class BookkeepingRecordsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'bookkeepingRecords';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.bookkeeping_records');
    }

    #[Override]
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
            ->filters([])
            ->headerActions(
                [
                    AttachBookkeepingRecordAction::make(),
                    CreateBookkeepingRecordFromTransactionAction::make(),
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

    #[On('refresh')]
    public function refresh(): void
    {
    }
}
