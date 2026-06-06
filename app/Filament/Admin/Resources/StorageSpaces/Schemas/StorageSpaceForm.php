<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class StorageSpaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('location')
                ->label(__('labels.location'))
                ->required()
                ->maxLength(255),
            TextInput::make('number')
                ->label(__('labels.space_number'))
                ->required(),
        ]);
    }
}
