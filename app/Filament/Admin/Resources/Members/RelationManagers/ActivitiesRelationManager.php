<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\RelationManagers;

use App\Filament\Admin\Resources\Activities\ActivityResource;
use App\Models\Activity as ActivityModel;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $relatedResource = ActivityResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('labels.activity')),
                TextColumn::make('start_date')
                    ->label(__('labels.start_date'))
                    ->date(),
                TextColumn::make('end_date')
                    ->label(__('labels.end_date'))
                    ->date(),
            ])
            ->recordUrl(
                static fn (ActivityModel $record): string => ActivityResource::getUrl('edit', ['record' => $record])
            )
            ->headerActions([
                AttachAction::make()
                    ->recordSelectOptionsQuery(
                        /** @phpstan-ignore-next-line method.notFound */
                        static fn (Builder $query) => $query->active()
                    ),
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
        return __('labels.activities');
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.activities'));
    }

    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.activity'));
    }
}
