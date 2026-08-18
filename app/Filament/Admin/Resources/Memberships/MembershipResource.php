<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Memberships;

use App\Filament\Admin\Clusters\AdministrationSettings\AdministrationSettingsCluster;
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
use Override;
use UnitEnum;

final class MembershipResource extends Resource
{
    #[Override]
    protected static ?string $model = Membership::class;

    #[Override]
    protected static bool $isGloballySearchable = false;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MemberAdministration;

    #[Override]
    protected static ?string $cluster = AdministrationSettingsCluster::class;

    #[Override]
    protected static ?int $navigationSort = 3;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    #[Override]
    protected static ?string $recordTitleAttribute = 'Membership';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return MembershipForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return MembershipsTable::configure($table);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListMemberships::route('/'),
            'create' => CreateMembership::route('/create'),
            'edit' => EditMembership::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.memberships');
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.membership');
    }
}
