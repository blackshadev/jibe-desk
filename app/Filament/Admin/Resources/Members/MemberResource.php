<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\Members\Pages\CreateMember;
use App\Filament\Admin\Resources\Members\Pages\EditMember;
use App\Filament\Admin\Resources\Members\Pages\ListMembers;
use App\Filament\Admin\Resources\Members\RelationManagers\ActivitiesRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\BillableItemInstancesRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\InvoicesRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\MemberObjectsRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\HouseholdMembersRelationManager;
use App\Filament\Admin\Resources\Members\Schemas\MemberForm;
use App\Filament\Admin\Resources\Members\Tables\MembersTable;
use App\Models\Member;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MemberAdministration;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MemberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            HouseholdMembersRelationManager::make(),
            InvoicesRelationManager::make(),
            BillableItemInstancesRelationManager::make(),
            ActivitiesRelationManager::make(),
            MemberObjectsRelationManager::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }

    public static function getPluralLabel(): string
    {
        return __('labels.members');
    }

    public static function getLabel(): string
    {
        return __('labels.member');
    }
}
