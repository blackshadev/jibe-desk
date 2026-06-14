<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InvoiceBatches;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\InvoiceBatches\Pages\CreateInvoiceBatch;
use App\Filament\Admin\Resources\InvoiceBatches\Pages\EditInvoiceBatch;
use App\Filament\Admin\Resources\InvoiceBatches\Pages\ListInvoiceBatches;
use App\Filament\Admin\Resources\InvoiceBatches\Schemas\InvoiceBatchForm;
use App\Filament\Admin\Resources\InvoiceBatches\Tables\InvoiceBatchesTable;
use App\Models\InvoiceBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;
use UnitEnum;

final class InvoiceBatchResource extends Resource
{
    protected static ?string $model = InvoiceBatch::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Invoicing;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentDuplicate;

    protected static ?string $recordTitleAttribute = 'id';

    protected static bool $isGloballySearchable = false;

    #[Override]
    public static function table(Table $table): Table
    {
        return InvoiceBatchesTable::configure($table);
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return InvoiceBatchForm::configure($schema);
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.invoice_batches');
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.invoice_batch');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListInvoiceBatches::route('/'),
            'create' => CreateInvoiceBatch::route('/create'),
            'edit' => EditInvoiceBatch::route('/{record}/edit'),
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record === null ? 'N/A' : 'Batch-' . $record->id;
    }
}
