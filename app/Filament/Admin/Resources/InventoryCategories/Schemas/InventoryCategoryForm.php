<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InventoryCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('labels.name'))
                            ->required()
                            ->unique(ignoreRecord: true),
                    ]),
            ]);
    }
}
