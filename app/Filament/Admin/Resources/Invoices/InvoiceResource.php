<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Admin\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Admin\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Admin\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Admin\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Invoicing;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentCurrencyEuro;

    protected static ?string $recordTitleAttribute = 'invoice_number';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.invoices');
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.invoice');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'edit' => EditInvoice::route('/{record}/edit'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
