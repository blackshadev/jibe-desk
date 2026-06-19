<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Schemas;

use App\Domain\Members\Gender;
use App\Models\Member;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

final class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->columns(2)
                    ->tabs([
                        Tabs\Tab::make(__('labels.personal_information'))
                            ->schema([
                                TextInput::make('first_name')
                                    ->label(__('labels.first_name'))
                                    ->required(),

                                TextInput::make('infix_name')
                                    ->label(__('labels.infix_name')),

                                TextInput::make('last_name')
                                    ->label(__('labels.last_name'))
                                    ->required(),

                                Select::make('gender')
                                    ->label(__('labels.gender'))
                                    ->options([
                                        Gender::Male->value => __('labels.genders.' . Gender::Male->value),
                                        Gender::Female->value => __('labels.genders.' . Gender::Female->value),
                                        Gender::NonBinary->value => __('labels.genders.' . Gender::NonBinary->value),
                                        Gender::Unknown->value => __('labels.genders.' . Gender::Unknown->value),
                                        Gender::Other->value => __('labels.genders.' . Gender::Other->value),
                                    ])
                                    ->required(),

                                DatePicker::make('birthdate')
                                    ->format('d-m-Y')
                                    ->native(false)
                                    ->label(__('labels.birthdate'))
                                    ->required(),

                                TextInput::make('age')
                                    ->formatStateUsing(static fn (?Member $record) => $record?->age)
                                    ->disabled()
                                    ->label(__('labels.age'))
                                    ->required(),
                            ]),

                        Tabs\Tab::make(__('labels.membership_information'))
                            ->schema([
                                Select::make('membership')
                                    ->label(__('labels.membership'))
                                    ->relationship('membership', 'name')
                                    ->required(),

                                Toggle::make('is_volunteer')
                                    ->columnSpanFull()
                                    ->label(__('labels.is_volunteer')),
                            ]),

                        Tabs\Tab::make(__('labels.address_information'))
                            ->columns(12)
                            ->disabled(static fn (): bool => !auth()->user()?->can('update_member_address_information'))
                            ->schema([
                                TextInput::make('address_street')
                                    ->columnSpan(6)
                                    ->required()
                                    ->label(__('labels.address_street')),

                                TextInput::make('address_housenumber')
                                    ->columnSpan(3)
                                    ->required()
                                    ->label(__('labels.address_housenumber')),

                                TextInput::make('address_housenumber_addition')
                                    ->columnSpan(3)
                                    ->label(__('labels.address_housenumber_addition')),

                                TextInput::make('address_postalcode')
                                    ->required()
                                    ->columnSpan(6)
                                    ->helperText(__('labels.address_postalcode_format'))
                                    ->regex('/^\d{4}[A-Z]{2}$/')
                                    ->label(__('labels.address_postalcode')),

                                TextInput::make('address_city')
                                    ->required()
                                    ->columnSpan(6)
                                    ->label(__('labels.address_city')),
                            ])
                            ->visible(static fn (): bool => auth()->user()?->can('view_member_address_information') ?? false),

                        Tabs\Tab::make(__('labels.payment_information'))
                            ->schema([
                                Grid::make()
                                    ->relationship('paymentInformation')
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->disabled(static fn (): bool => !auth()->user()?->can('update_member_payment_information'))
                                    ->schema([
                                        TextInput::make('banking_account_number')
                                            ->label(__('labels.banking_account_number'))
                                            ->maxLength(34),

                                        TextInput::make('banking_bic')
                                            ->label(__('labels.banking_bic'))
                                            ->maxLength(11),

                                        TextInput::make('banking_account_holder_name')
                                            ->label(__('labels.banking_account_holder_name'))
                                            ->columnSpanFull()
                                            ->maxLength(255),

                                        DatePicker::make('mandate_accepted_date')
                                            ->format('d-m-Y')
                                            ->native(false)
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->label(__('labels.mandate_date')),

                                        TextInput::make('uuid')
                                            ->label(__('labels.reference'))
                                            ->disabled()
                                            ->dehydrated(false),
                                    ]),
                            ])
                            ->visible(static fn (): bool => auth()->user()?->can('view_member_payment_information') ?? false),

                        Tabs\Tab::make(__('labels.registration_details'))
                            ->schema([
                                DatePicker::make('created_at')
                                    ->label(__('labels.created_at'))
                                    ->native(false)
                                    ->required()
                                    ->disabled(),
                                Tabs::make()
                                    ->vertical()
                                    ->columnSpanFull()
                                    ->schema([
                                        Tabs\Tab::make(__('labels.membership_information'))
                                            ->schema([
                                                KeyValue::make('registration_data.membership')
                                                    ->columnSpanFull()
                                                    ->hiddenLabel()
                                                    ->disabled(),
                                            ]),
                                        Tabs\Tab::make(__('labels.personal_information'))
                                            ->schema([
                                                KeyValue::make('registration_data.personalInfo')
                                                    ->columnSpanFull()
                                                    ->hiddenLabel()
                                                    ->disabled(),
                                            ]),
                                        Tabs\Tab::make(__('labels.payment_information'))
                                            ->schema([
                                                KeyValue::make('registration_data.paymentInfo')
                                                    ->columnSpanFull()
                                                    ->hiddenLabel()
                                                    ->disabled(),
                                            ]),
                                    ]),
                            ])
                            ->visible(static fn (): bool => auth()->user()?->can('view_member_registration_data') ?? false),
                    ]),
            ]);
    }
}
