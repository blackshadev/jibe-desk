<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MemberObjectTypes;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\MemberObjectTypes\Pages\CreateMemberObjectType;
use App\Filament\Admin\Resources\MemberObjectTypes\Pages\EditMemberObjectType;
use App\Filament\Admin\Resources\MemberObjectTypes\Pages\ListMemberObjectTypes;
use App\Filament\Admin\Resources\MemberObjectTypes\Schemas\MemberObjectTypeForm;
use App\Filament\Admin\Resources\MemberObjectTypes\Tables\MemberObjectTypesTable;
use App\Models\MemberObjectType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class MemberObjectTypeResource extends Resource
{
    protected static bool $isGloballySearchable = false;

    protected static ?string $model = MemberObjectType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Tag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MemberAdministration;

    public static function form(Schema $schema): Schema
    {
        return MemberObjectTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MemberObjectTypesTable::configure($table);
    }

    public static function getLabel(): string
    {
        return __('labels.member_object_type');
    }

    public static function getPluralLabel(): string
    {
        return __('labels.member_object_types');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberObjectTypes::route('/'),
            'create' => CreateMemberObjectType::route('/create'),
            'edit' => EditMemberObjectType::route('/{record}/edit'),
        ];
    }
}
