<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Activities\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class ActivityMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('labels.name')),
                TextColumn::make('pivot.created_at')
                    ->label(__('labels.created_at'))
                    ->dateTime(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->multiple()
                    ->recordSelectSearchColumns([
                        'last_name',
                        'first_name',
                        'infix_name',
                    ]),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.participants');
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.participant'));
    }

    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.participants'));
    }
}
