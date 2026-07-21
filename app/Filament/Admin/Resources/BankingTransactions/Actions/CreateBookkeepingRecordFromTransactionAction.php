<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\Invoices\Formatters\PriceFormatter;
use App\Filament\Admin\Resources\BankingTransactions\Helpers\IsOpen;
use App\Models\BankingTransaction;
use App\Models\BookkeepingRecord;
use App\Models\CostCenter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;

final class CreateBookkeepingRecordFromTransactionAction
{
    public static function make(): Action
    {
        return Action::make('createBookkeepingRecordFromTransaction')
            ->label(__('labels.create_bookkeeping_record_from_transaction'))
            ->modalHeading(__('labels.create_bookkeeping_record_from_transaction'))
            ->visible(IsOpen::checkOwner(...))
            ->schema(static function (RelationManager $livewire): array {
                /** @var BankingTransaction $record */
                $record = $livewire->getOwnerRecord();

                return [
                    TextInput::make('year')
                        ->label(__('labels.book_year'))
                        ->default(now()->year)
                        ->numeric()
                        ->required(),
                    Select::make('cost_center_id')
                        ->label(__('labels.cost_center'))
                        ->options(static fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('description')
                        ->label(__('labels.description'))
                        ->default($record->description)
                        ->required(),
                    TextInput::make('amount')
                        ->label(__('labels.price'))
                        ->prefix('€')
                        ->default(PriceFormatter::formatSignless((float) $record->unmatched_amount))
                        ->regex('/^-?[0-9,.]+((\.|,)\d{0,3})?$/')
                        ->dehydrateStateUsing(PriceFormatter::parse(...))
                        ->required(),
                    TextInput::make('amount_vat')
                        ->label(__('labels.price_vat'))
                        ->prefix('€')
                        ->default(PriceFormatter::formatSignless((float) $record->unmatched_amount * 0.21))
                        ->regex('/^-?[0-9,.]+((\.|,)\d{0,3})?$/')
                        ->dehydrateStateUsing(PriceFormatter::parse(...))
                        ->required(),
                ];
            })
            ->action(static function (array $data, RelationManager $livewire, BankTransactionRepository $repository): void {
                /** @var BankingTransaction $record */
                $record = $livewire->getOwnerRecord();

                $bookkeepingRecord = BookkeepingRecord::create([
                    'year' => $data['year'],
                    'cost_center_id' => $data['cost_center_id'],
                    'description' => $data['description'],
                    'amount_price' => $data['amount'],
                    'amount_vat' => $data['amount_vat'],
                    'banking_transaction_id' => $record->id,
                ]);

                $repository->attachBookkeepingRecord(
                    BankTransactionId::create($record->id),
                    $bookkeepingRecord->id,
                );

                $livewire->dispatch('refresh');
            })
            ->successNotificationTitle(__('notifications.bookkeeping_record_created_and_attached'));
    }
}
