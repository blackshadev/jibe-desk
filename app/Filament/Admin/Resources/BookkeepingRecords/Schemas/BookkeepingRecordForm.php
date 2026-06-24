<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BookkeepingRecords\Schemas;

use App\Domain\Invoices\CompoundPrice;
use App\Formatters\PriceFormatter;
use App\Models\BookkeepingRecord;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BookkeepingRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('year')
                    ->label(__('labels.book_year'))
                    ->required()
                    ->default(now()->year)
                    ->numeric(),
                Select::make('cost_center_id')
                    ->label(__('labels.cost_center'))
                    ->relationship('costCenter', 'title')
                    ->required(),
                TextInput::make('description')
                    ->label(__('labels.description'))
                    ->required(),
                TextInput::make('amount')
                    ->label(__('labels.price'))
                    ->required()
                    ->prefix('€')
                    ->regex('/^\d+((\.|,)\d{0,3})?$/')
                    ->formatStateUsing(static fn (?BookkeepingRecord $record) => PriceFormatter::formatCompoundSignless($record?->amount))
                    ->dehydrateStateUsing(static fn (string $state): CompoundPrice => CompoundPrice::create(PriceFormatter::parse($state))),
            ]);
    }
}
