<?php

declare(strict_types=1);

namespace App\Filament\Admin\Actions;

use App\Domain\Bookkeeping\CostCenterBudgetRepository;
use App\Models\CostCenterBudget;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

final class CreateCostCenterBudgetsAction
{
    public static function make(): Action
    {
        return Action::make('createCostCenterBudgets')
            ->label(__('labels.create_budgets_for_year'))
            ->modalHeading(__('labels.create_budgets_for_year'))
            ->visible(static fn (): bool => auth()->user()?->can('create', CostCenterBudget::class) ?? false)
            ->schema([
                TextInput::make('year')
                    ->label(__('labels.book_year'))
                    ->required()
                    ->numeric()
                    ->minValue(2000)
                    ->maxValue(2100)
                    ->default(now()->year),
            ])
            ->action(static function (array $data, CostCenterBudgetRepository $repository): void {
                $created = $repository->createZeroBudgetsForYear((int) $data['year']);

                Notification::make()
                    ->title(__('labels.budgets_created'))
                    ->body(
                        $created === 0
                            ? __('labels.budgets_created_none')
                            : __('labels.budgets_created_result', ['count' => $created]),
                    )
                    ->status($created === 0 ? 'warning' : 'success')
                    ->send();
            });
    }
}
