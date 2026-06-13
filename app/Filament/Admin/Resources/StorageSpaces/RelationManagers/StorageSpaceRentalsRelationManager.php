<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\RelationManagers;

use App\Filament\Admin\Resources\StorageSpaces\Schemas\StorageSpaceRentalForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;

final class StorageSpaceRentalsRelationManager extends RelationManager
{
    protected static string $relationship = 'rentals';

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.name')->label(__('labels.member')),
                TextColumn::make('start_date')->label(__('labels.start_date'))->date(),
                TextColumn::make('end_date')->label(__('labels.end_date'))->date(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->schema(StorageSpaceRentalForm::forStorageSpace(...)),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(StorageSpaceRentalForm::forStorageSpace(...)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.storage_space_rentals');
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.storage_space_rental'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.storage_space_rentals'));
    }
}
