<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches;

use App\Filament\Admin\Clusters\Invoicing\InvoicingCluster;
use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\SepaBatches\Pages\CreateSepaBatch;
use App\Filament\Admin\Resources\SepaBatches\Pages\EditSepaBatch;
use App\Filament\Admin\Resources\SepaBatches\Pages\ListSepaBatches;
use App\Filament\Admin\Resources\SepaBatches\Schemas\SepaBatchForm;
use App\Filament\Admin\Resources\SepaBatches\Tables\SepaBatchesTable;
use App\Models\SepaBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;
use UnitEnum;
use Webmozart\Assert\Assert;

final class SepaBatchResource extends Resource
{
    #[Override]
    protected static ?string $model = SepaBatch::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Invoicing;

    #[Override]
    protected static ?string $cluster = InvoicingCluster::class;

    #[Override]
    protected static ?int $navigationSort = 3;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentDuplicate;

    #[Override]
    protected static ?string $recordTitleAttribute = 'id';

    #[Override]
    protected static bool $isGloballySearchable = false;

    #[Override]
    public static function table(Table $table): Table
    {
        return SepaBatchesTable::configure($table);
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return SepaBatchForm::configure($schema);
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.sepa_batches');
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.sepa_batch');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListSepaBatches::route('/'),
            'create' => CreateSepaBatch::route('/create'),
            'edit' => EditSepaBatch::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getRecordTitle(?Model $record): string
    {
        Assert::nullOrIsInstanceOf($record, SepaBatch::class);
        return $record === null ? 'N/A' : 'SEPA ronde ' . $record->id;
    }
}
