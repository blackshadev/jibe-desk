<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Schemas;

use App\Formatters\PriceFormatter;
use App\Models\Member;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('labels.invoice_information'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('invoice_number')
                            ->label(__('labels.invoice_number'))
                            ->disabled(),
                        DatePicker::make('date')
                            ->label(__('labels.invoice_date'))
                            ->native(false)
                            ->format('d-m-Y')
                            ->disabled(),

                        Grid::make()
                            ->columnSpanFull()
                            ->schema([
                                Select::make('member_id')
                                    ->relationship(
                                        'member',
                                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('last_name')->orderBy('first_name')->orderBy('infix_name')
                                    )
                                    ->label(__('labels.member'))
                                    ->live(onBlur: true)
                                    ->getOptionLabelFromRecordUsing(fn (Member $record) => $record->name)
                                    ->searchable(['first_name', 'infix_name', 'last_name'])
                                    ->afterStateUpdated(static function (?int $state, Set $set) {
                                        if ($state === null) {
                                            return;
                                        }

                                        $set('recipient_name', Member::findOrFail($state)->name);
                                    }),
                            ]),

                        TextInput::make('recipient_address')
                            ->label(__('labels.recipient_address'))
                            ->required(),
                        TextInput::make('recipient_name')
                            ->label(__('labels.recipient_name'))
                            ->disabled(fn (Get $get) => $get('member_id') !== null)
                            ->required(),
                    ]),
                Section::make(__('labels.invoice_lines'))
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('lines')
                            ->hiddenLabel()
                            ->relationship()
                            ->collapsed()
                            ->itemLabel(fn (array $state) => empty($state['description']) ? '(leeg)' : sprintf('(%.2fx) %s %s', $state['quantity'], $state['description'], PriceFormatter::format((float)$state['price'])))
                            ->columns(2)
                            ->schema([
                                TextInput::make('description')
                                    ->label(__('labels.description'))
                                    ->columnSpanFull()
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label(__('labels.quantity'))
                                    ->default(1.00)
                                    ->required(),
                                TextInput::make('price')
                                    ->label(__('labels.price'))
                                    ->default(1.99)
                                    ->required(),
                            ])
                            ->mutateRelationshipDataBeforeSaveUsing(
                                static function (array $data): array {
                                    $data['vat'] = $data['price'] * 0.21;

                                    return $data;
                                }
                            )
                            ->mutateRelationshipDataBeforeCreateUsing(
                                static function (array $data): array {
                                    $data['vat'] = $data['price'] * 0.21;

                                    return $data;
                                }
                            ),
                    ]),
            ]);
    }
}
