<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Memberships;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\Memberships\Pages\CreateMembership;
use App\Filament\Admin\Resources\Memberships\Pages\EditMembership;
use App\Filament\Admin\Resources\Memberships\Pages\ListMemberships;
use App\Filament\Admin\Resources\Memberships\Schemas\MembershipForm;
use App\Filament\Admin\Resources\Memberships\Tables\MembershipsTable;
use App\Models\Membership;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class MembershipResource extends Resource
{
    protected static ?string $model = Membership::class;

    protected static bool $isGloballySearchable = false;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MemberAdministration;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Membership';

    public static function form(Schema $schema): Schema
    {
        return MembershipForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembershipsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberships::route('/'),
            'create' => CreateMembership::route('/create'),
            'edit' => EditMembership::route('/{record}/edit'),
        ];
    }

    public static function getPluralLabel(): string
    {
        return __('labels.memberships');
    }

    public static function getLabel(): string
    {
        return __('labels.membership');
    }
}
