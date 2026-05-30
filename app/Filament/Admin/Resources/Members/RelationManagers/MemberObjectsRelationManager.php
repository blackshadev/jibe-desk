<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\RelationManagers;

use App\Filament\Admin\Resources\MemberObjects\Schemas\MemberObjectForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class MemberObjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberObjects';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('memberObjectType.name')->label(__('labels.member_object_type')),
                TextColumn::make('name')->label(__('labels.name')),
                TextColumn::make('start_date')->label(__('labels.start_date'))->date(),
                TextColumn::make('end_date')->label(__('labels.end_date'))->date(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->schema(MemberObjectForm::configure(...)),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(MemberObjectForm::configure(...)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.member_objects');
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.member_object'));
    }

    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.member_objects'));
    }
}
