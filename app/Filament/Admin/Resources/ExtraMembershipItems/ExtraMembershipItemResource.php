<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExtraMembershipItems;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\ExtraMembershipItems\Pages\EditExtraMembershipItem;
use App\Filament\Admin\Resources\ExtraMembershipItems\Pages\ListExtraMembershipItems;
use App\Filament\Admin\Resources\ExtraMembershipItems\Schemas\ExtraMembershipItemForm;
use App\Filament\Admin\Resources\ExtraMembershipItems\Tables\ExtraMembershipItemsTable;
use App\Models\ExtraMembershipItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;
use UnitEnum;

final class ExtraMembershipItemResource extends Resource
{
    protected static bool $isGloballySearchable = false;

    protected static ?string $model = ExtraMembershipItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::PlusCircle;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Technical;

    protected static ?string $recordTitleAttribute = 'code';

    #[Override]
    public static function getRecordTitle(?Model $record): string
    {
        /** @var ExtraMembershipItem $record */
        return $record->code->value;
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return ExtraMembershipItemForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return ExtraMembershipItemsTable::configure($table);
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.extra_membership_item');
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.extra_membership_items');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListExtraMembershipItems::route('/'),
            'edit' => EditExtraMembershipItem::route('/{record}/edit'),
        ];
    }
}
