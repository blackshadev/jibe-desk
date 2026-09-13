<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Filament\Admin\Resources\BankingTransactions\Helpers\GetTransaction;
use App\Filament\Admin\Resources\BankingTransactions\Helpers\IsOpen;
use App\Models\BankingTransaction;
use App\Models\BankStatement;
use App\Models\BookkeepingRecord;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;

final class AttachBookkeepingRecordAction
{
    public static function make(): Action
    {
        return Action::make('attachBookkeepingRecord')
            ->label(__('labels.attach_bookkeeping_record'))
            ->icon(Heroicon::BookOpen)
            ->modalHeading(__('labels.attach_bookkeeping_record'))
            ->visible(IsOpen::checkOwner(...))
            ->schema([
                Select::make('bookkeeping_record_id')
                    ->label(__('labels.bookkeeping_record'))
                    ->options(static fn () => BookkeepingRecord::query()
                        ->orderBy('description')
                        ->get()
                        ->mapWithKeys(static fn (BookkeepingRecord $record): array => [
                            $record->id => sprintf('%s - %s', $record->year, $record->description),
                        ]))
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->action(static function (array $data, RelationManager $livewire, BankingTransaction|BankStatement|null $record, BankTransactionRepository $repository): void {
                $record = GetTransaction::get($livewire, $record);
                $repository->attachBookkeepingRecord(
                    BankTransactionId::create((int) $record->id),
                    (int) $data['bookkeeping_record_id'],
                );
            })
            ->successNotificationTitle(__('labels.attached'));
    }
}
