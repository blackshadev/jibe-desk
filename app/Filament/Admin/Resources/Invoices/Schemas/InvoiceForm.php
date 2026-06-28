<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Schemas;

use App\Domain\Invoices\Formatters\PriceFormatter;
use App\Filament\Admin\Labels\InvoiceStatusLabels;
use App\Models\CostCenter;
use App\Models\Member;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                            ->format('d-m-Y'),

                        Select::make('status')
                            ->label(__('labels.status'))
                            ->options(InvoiceStatusLabels::options())
                            ->disabled(),
                    ]),
                Section::make(__('labels.recipient'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('member_id')
                            ->relationship(
                                'member',
                                modifyQueryUsing: static fn (Builder $query) => $query->orderBy('last_name')->orderBy('first_name')->orderBy('infix_name'),
                            )
                            ->label(__('labels.member'))
                            ->live(onBlur: true)
                            ->getOptionLabelFromRecordUsing(static fn (Member $record) => $record->name)
                            ->searchable(['first_name', 'infix_name', 'last_name'])
                            ->afterStateUpdated(static function (?int $state, Set $set) {
                                if ($state === null) {
                                    return;
                                }
                                $member = Member::findOrFail($state);

                                $set('recipient_address', $member->address);
                                $set('recipient_name', $member->name);
                                $set('recipient_email', $member->email);
                            }),

                        TextInput::make('recipient_email')
                            ->label(__('labels.recipient_email'))
                            ->disabled(static fn (Get $get) => $get('member_id') !== null)
                            ->required(),

                        TextInput::make('recipient_address')
                            ->label(__('labels.recipient_address'))
                            ->disabled(static fn (Get $get) => $get('member_id') !== null)
                            ->required(),
                        TextInput::make('recipient_name')
                            ->label(__('labels.recipient_name'))
                            ->disabled(static fn (Get $get) => $get('member_id') !== null)
                            ->required(),
                    ]),

                Section::make(__('labels.invoice_lines'))
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('lines')
                            ->hiddenLabel()
                            ->relationship()
                            ->collapsed()
                            ->itemLabel(static fn (array $state) => $state['description'] === ''
                                ? '(leeg)'
                                : sprintf('(%.2fx) %s %s', $state['quantity'], $state['description'], PriceFormatter::format((float) $state['price'])))
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
                                Select::make('cost_center_id')
                                    ->label(__('labels.cost_center'))
                                    ->options(static fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])
                            ->mutateRelationshipDataBeforeSaveUsing(
                                static function (array $data): array {
                                    $data['vat'] = $data['price'] * 0.21;

                                    return $data;
                                },
                            )
                            ->mutateRelationshipDataBeforeCreateUsing(
                                static function (array $data): array {
                                    $data['vat'] = $data['price'] * 0.21;

                                    return $data;
                                },
                            ),
                    ]),
            ]);
    }
}
