<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class StorageSpaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('storage_space_location_id')
                ->label(__('labels.location'))
                ->relationship('location', 'name')
                ->required(),
            TextInput::make('number')
                ->label(__('labels.space_number'))
                ->required(),
        ]);
    }
}
