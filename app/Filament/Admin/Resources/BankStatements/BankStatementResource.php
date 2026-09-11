<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankStatements;

use App\Filament\Admin\Clusters\Bookkeeping\BookkeepingCluster;
use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\BankStatements\Pages\ListBankStatements;
use App\Filament\Admin\Resources\BankStatements\Pages\ViewBankStatement;
use App\Filament\Admin\Resources\BankStatements\Schemas\BankStatementInfolist;
use App\Filament\Admin\Resources\BankStatements\Tables\BankStatementsTable;
use App\Models\BankStatement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class BankStatementResource extends Resource
{
    #[Override]
    protected static ?string $model = BankStatement::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentCurrencyEuro;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    #[Override]
    protected static ?string $cluster = BookkeepingCluster::class;

    #[Override]
    protected static ?int $navigationSort = 3;

    #[Override]
    protected static ?string $recordTitleAttribute = 'statement_number';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return BankStatementInfolist::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return BankStatementsTable::configure($table);
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.bank_statements');
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.bank_statement');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListBankStatements::route('/'),
            'view' => ViewBankStatement::route('/{record}'),
        ];
    }
}
