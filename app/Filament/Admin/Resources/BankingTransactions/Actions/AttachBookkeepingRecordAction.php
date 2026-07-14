<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Models\BookkeepingRecord;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

final class AttachBookkeepingRecordAction
{
    public static function make(): Action
    {
        return Action::make('attachBookkeepingRecord')
            ->label(__('labels.attach_bookkeeping_record'))
            ->modalHeading(__('labels.attach_bookkeeping_record'))
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
            ->action(static function (array $data, mixed $record, BankTransactionRepository $repository): void {
                $repository->attachBookkeepingRecord(
                    BankTransactionId::create((int) $record->id),
                    (int) $data['bookkeeping_record_id'],
                );
            })
            ->successNotificationTitle(__('labels.attached'));
    }
}
