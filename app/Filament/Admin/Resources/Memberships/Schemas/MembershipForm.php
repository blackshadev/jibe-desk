<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Memberships\Schemas;

use App\Filament\Admin\Labels\BillPeriodLabels;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class MembershipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('labels.name'))
                            ->required(),
                    ]),
                Section::make(__('labels.billing'))
                    ->relationship('billableItem')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('description')
                            ->label(__('labels.description'))
                            ->required(),
                        TextInput::make('price')
                            ->label(__('labels.price'))
                            ->required(),
                        Select::make('bill_period')
                            ->label(__('labels.bill_period'))
                            ->options(BillPeriodLabels::options())
                            ->required(),
                    ])
                    ->mutateRelationshipDataBeforeCreateUsing(static fn (array $data): array => [
                        ...$data,
                        'vat' => $data['price'] * 0.21,
                    ]),
            ]);
    }
}
