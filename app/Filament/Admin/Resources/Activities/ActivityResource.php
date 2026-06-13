<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Activities;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\Activities\Pages\CreateActivity;
use App\Filament\Admin\Resources\Activities\Pages\EditActivity;
use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Filament\Admin\Resources\Activities\RelationManagers\ActivityMembersRelationManager;
use App\Filament\Admin\Resources\Activities\Schemas\ActivityForm;
use App\Filament\Admin\Resources\Activities\Tables\ActivitiesTable;
use App\Models\Activity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Activities;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CalendarDays;

    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return ActivityForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            ActivityMembersRelationManager::make(),
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'create' => CreateActivity::route('/create'),
            'edit' => EditActivity::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.activities');
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.activity');
    }
}
