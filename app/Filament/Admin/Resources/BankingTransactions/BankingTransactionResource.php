<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\BankingTransactions\Pages\CreateBankingTransaction;
use App\Filament\Admin\Resources\BankingTransactions\Pages\EditBankingTransaction;
use App\Filament\Admin\Resources\BankingTransactions\Pages\ListBankingTransactions;
use App\Filament\Admin\Resources\BankingTransactions\Pages\ViewBankingTransaction;
use App\Filament\Admin\Resources\BankingTransactions\Schemas\BankingTransactionForm;
use App\Filament\Admin\Resources\BankingTransactions\Tables\BankingTransactionsTable;
use App\Models\BankingTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class BankingTransactionResource extends Resource
{
    protected static ?string $model = BankingTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    protected static ?string $recordTitleAttribute = 'description';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return BankingTransactionForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return BankingTransactionsTable::configure($table);
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.banking_transactions');
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.banking_transaction');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListBankingTransactions::route('/'),
            'create' => CreateBankingTransaction::route('/create'),
            'view' => ViewBankingTransaction::route('/{record}'),
        ];
    }


}
