<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Schemas;

use App\Domain\Billing\Payment\MandateExpirationStatus;
use App\Domain\Members\Gender;
use App\Models\Member;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

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

                                TextInput::make('email')
                                    ->label(__('labels.email'))
                                    ->email()
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
                                    ->label(__('labels.is_volunteer')),

                                DateTimePicker::make('stopped_at')
                                    ->label(__('labels.stopped_at'))
                                    ->readonly()
                                    ->disabled()
                                    ->date()
                                    ->native(false),

                                DateTimePicker::make('accepted_at')
                                    ->label(__('labels.accepted_at'))
                                    ->readonly()
                                    ->disabled()
                                    ->date()
                                    ->native(false),

                                TextInput::make('user.email')
                                    ->label(__('labels.account'))
                                    ->formatStateUsing(static fn (?Member $record): ?string => $record?->user?->email)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(static fn (string $operation): bool => $operation === Operation::Edit->value),
                            ]),

                        Tabs\Tab::make(__('labels.address_information'))
                            ->columns(12)
                            ->disabled(static fn (Member $record): bool => !auth()->user()?->can('update-address-information', $record))
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
                            ->visible(static fn (Member $record): bool => auth()->user()?->can('view-address-information', $record) ?? false),

                        self::paymentTab(),

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
                            ->visible(static fn (Member $record): bool => auth()->user()?->can('view-registration-data', $record) ?? false),
                    ]),
            ]);
    }

    private static function paymentTab(): Tab
    {
        return Tabs\Tab::make(__('labels.payment_information'))
            ->schema([
                TextEntry::make('mandate_reference')
                    ->label(__('labels.mandate_reference'))
                    ->state(static fn (?Member $record): string => $record?->paymentInformation->mandate_reference ?? __('labels.no_active_mandate')),

                TextEntry::make('mandate_state')
                    ->label(__('labels.mandate_state'))
                    ->state(static fn (?Member $record): string => $record?->paymentInformation === null
                        ? __('labels.no_active_mandate')
                        : __('labels.mandate_states.' . $record->paymentInformation->expiration_status->value)),

                TextEntry::make('active_mandate_iban')
                    ->label(__('labels.banking_account_number'))
                    ->state(static fn (?Member $record): string => $record?->paymentInformation->banking_account_number ?? '—'),

                TextEntry::make('active_mandate_bic')
                    ->label(__('labels.banking_bic'))
                    ->state(static fn (?Member $record): string => $record?->paymentInformation->banking_bic ?? '—'),

                TextEntry::make('active_mandate_holder')
                    ->label(__('labels.banking_account_holder_name'))
                    ->columnSpanFull()
                    ->state(static fn (?Member $record): string => $record?->paymentInformation->banking_account_holder_name ?? '—'),

                TextEntry::make('active_mandate_signed_at')
                    ->label(__('labels.mandate_date'))
                    ->state(static fn (?Member $record): ?string => $record?->paymentInformation?->mandate_accepted_date?->format('d-m-Y')),

                TextEntry::make('active_mandate_last_collected_at')
                    ->label(__('labels.last_collected_at'))
                    ->state(static fn (?Member $record): string => $record?->paymentInformation?->last_collected_at?->format('d-m-Y') ?? '—'),

                TextEntry::make('active_mandate_expires_at')
                    ->label(__('labels.mandate_expires_at'))
                    ->state(static fn (?Member $record): string => $record?->paymentInformation === null
                        ? '—'
                        : $record->paymentInformation->expires_at->format('d-m-Y')),

                TextEntry::make('mandate_expired_notice')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->visible(static fn (?Member $record): bool => $record?->paymentInformation !== null
                        && $record->paymentInformation->expiration_status === MandateExpirationStatus::Expired)
                    ->state(__('labels.mandate_expired_notice')),
            ])
            ->visible(static fn (Member $record): bool => auth()->user()?->can('view-payment-information', $record) ?? false);
    }
}
