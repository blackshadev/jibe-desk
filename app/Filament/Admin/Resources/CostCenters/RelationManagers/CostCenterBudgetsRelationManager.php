<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\RelationManagers;

use App\Filament\Admin\Resources\CostCenters\Schemas\CostCenterBudgetForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;

final class CostCenterBudgetsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'budgets';

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year')
                    ->label(__('labels.book_year'))
                    ->sortable(),
                TextColumn::make('starting_amount')
                    ->label(__('labels.starting_amount'))
                    ->money('EUR')
                    ->alignEnd(),
                TextColumn::make('budget_amount')
                    ->label(__('labels.budget'))
                    ->money('EUR')
                    ->alignEnd(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->schema(CostCenterBudgetForm::configure(...)),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(CostCenterBudgetForm::configure(...)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.budgets');
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.budget'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.budgets'));
    }
}
