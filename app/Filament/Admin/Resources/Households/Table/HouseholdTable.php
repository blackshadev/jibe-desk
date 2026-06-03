<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Households\Table;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class HouseholdTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('members.name')
                ->label(__('labels.household_members'))
                ->badge(),
        ]);
    }
}
