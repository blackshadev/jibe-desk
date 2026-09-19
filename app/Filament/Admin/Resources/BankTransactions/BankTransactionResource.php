<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions;

use App\Filament\Admin\Clusters\Bookkeeping\BookkeepingCluster;
use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\BankTransactions\Pages\CreateBankTransaction;
use App\Filament\Admin\Resources\BankTransactions\Pages\EditBankTransaction;
use App\Filament\Admin\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Filament\Admin\Resources\BankTransactions\Pages\ViewBankTransaction;
use App\Filament\Admin\Resources\BankTransactions\Schemas\BankTransactionForm;
use App\Filament\Admin\Resources\BankTransactions\Tables\BankTransactionsTable;
use App\Models\BankTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class BankTransactionResource extends Resource
{
    #[Override]
    protected static ?string $model = BankTransaction::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    #[Override]
    protected static ?string $cluster = BookkeepingCluster::class;

    #[Override]
    protected static ?int $navigationSort = 3;

    #[Override]
    protected static ?string $recordTitleAttribute = 'description';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return BankTransactionForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return BankTransactionsTable::configure($table);
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.bank_transactions');
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.bank_transaction');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListBankTransactions::route('/'),
            'create' => CreateBankTransaction::route('/create'),
            'edit' => EditBankTransaction::route('/{record}/edit'),
            'view' => ViewBankTransaction::route('/{record}'),
        ];
    }
}
