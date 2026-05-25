<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExtraMembershipItems\Schemas;

use App\Domain\Invoices\Billing\BillPeriod;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

final class ExtraMembershipItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('code')
                            ->label(__('labels.code'))
                            ->disabledOn(Operation::Edit)
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
                            ->options([
                                BillPeriod::Monthly->value => __('labels.bill_periods.monthly'),
                                BillPeriod::Quarterly->value => __('labels.bill_periods.quarterly'),
                                BillPeriod::Annually->value => __('labels.bill_periods.annually'),
                            ])
                            ->required(),
                    ])
                    ->mutateRelationshipDataBeforeCreateUsing(static fn (array $data): array => [
                        ...$data,
                        'vat' => $data['price'] * 0.21,
                    ]),
            ]);
    }
}
