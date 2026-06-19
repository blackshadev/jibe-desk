<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Labels\RoleLabels;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('labels.personal_information'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('labels.name'))
                            ->required(),

                        TextInput::make('email')
                            ->label(__('labels.email'))
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                    ]),

                Section::make(__('labels.password'))
                    ->schema([
                        TextInput::make('password')
                            ->label(__('labels.password'))
                            ->password()
                            ->revealable()
                            ->required(static fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(static fn (?string $state): bool => filled($state))
                            ->maxLength(255),
                    ]),

                Section::make(__('labels.roles'))
                    ->schema([
                        CheckboxList::make('roles')
                            ->label(__('labels.roles'))
                            ->relationship('roles', 'name')
                            ->getOptionLabelFromRecordUsing(
                                static fn (Role $record): string => RoleLabels::label(RoleName::from($record->name)),
                            )
                            ->columns()
                            ->bulkToggleable(),
                    ]),
            ]);
    }
}
