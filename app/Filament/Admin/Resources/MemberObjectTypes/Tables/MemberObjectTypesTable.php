<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MemberObjectTypes\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class MemberObjectTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name'),
            TextColumn::make('billableItem.description')->label(__('labels.description')),
            TextColumn::make('billableItem.price')->label(__('labels.price'))->formatStateUsing(\App\Formatters\PriceFormatter::format(...)),
        ]);
    }
}
