<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankAccounts;

use App\Filament\Admin\Clusters\Bookkeeping\BookkeepingCluster;
use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\BankAccounts\Pages\CreateBankAccount;
use App\Filament\Admin\Resources\BankAccounts\Pages\EditBankAccount;
use App\Filament\Admin\Resources\BankAccounts\Pages\ListBankAccounts;
use App\Filament\Admin\Resources\BankAccounts\Pages\ViewBankAccount;
use App\Filament\Admin\Resources\BankAccounts\Schemas\BankAccountForm;
use App\Filament\Admin\Resources\BankAccounts\Tables\BankAccountsTable;
use App\Models\BankAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class BankAccountResource extends Resource
{
    #[Override]
    protected static ?string $model = BankAccount::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingLibrary;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    #[Override]
    protected static ?string $cluster = BookkeepingCluster::class;

    #[Override]
    protected static ?int $navigationSort = 5;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return BankAccountForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return BankAccountsTable::configure($table);
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.bank_account');
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.bank_accounts');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListBankAccounts::route('/'),
            'create' => CreateBankAccount::route('/create'),
            'edit' => EditBankAccount::route('/{record}/edit'),
            'view' => ViewBankAccount::route('/{record}'),
        ];
    }
}
