<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BookkeepingRecords;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\BookkeepingRecords\Pages\CreateBookkeepingRecord;
use App\Filament\Admin\Resources\BookkeepingRecords\Pages\EditBookkeepingRecord;
use App\Filament\Admin\Resources\BookkeepingRecords\Pages\ListBookkeepingRecords;
use App\Filament\Admin\Resources\BookkeepingRecords\Pages\ViewBookkeepingRecord;
use App\Filament\Admin\Resources\BookkeepingRecords\Schemas\BookkeepingRecordForm;
use App\Filament\Admin\Resources\BookkeepingRecords\Tables\BookkeepingRecordsTable;
use App\Models\BookkeepingRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BookkeepingRecordResource extends Resource
{
    protected static ?string $model = BookkeepingRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BookOpen;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return BookkeepingRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BookkeepingRecordsTable::configure($table);
    }

    public static function getLabel(): string
    {
        return __('labels.bookkeeping_record');
    }

    public static function getPluralLabel(): string
    {
        return __('labels.bookkeeping_records');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookkeepingRecords::route('/'),
            'create' => CreateBookkeepingRecord::route('/create'),
            'edit' => EditBookkeepingRecord::route('/{record}/edit'),
            'view' => ViewBookkeepingRecord::route('/{record}'),
        ];
    }
}
