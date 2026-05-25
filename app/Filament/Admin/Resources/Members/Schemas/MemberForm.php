<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Schemas;

use App\Domain\Members\Gender;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('labels.personal_information'))
                    ->columnSpanFull()
                    ->columns(2)
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
                                Gender::Undetermined->value => __('labels.genders.' . Gender::Undetermined->value),
                                Gender::Other->value => __('labels.genders.' . Gender::Other->value),
                            ])
                            ->required(),

                        DatePicker::make('birthdate')
                            ->format('d-m-Y')
                            ->native(false)
                            ->label(__('labels.birthdate'))
                            ->required(),

                    ]),

                Section::make(__('labels.membership_information'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([

                        Select::make('membership')
                            ->label(__('labels.membership'))
                            ->relationship('membership', 'name')
                            ->required(),

                        Toggle::make('is_volunteer')
                            ->label(__('labels.is_volunteer')),
                    ]),
            ]);
    }
}
