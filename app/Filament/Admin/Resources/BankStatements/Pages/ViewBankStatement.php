<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankStatements\Pages;

use App\Filament\Admin\Resources\BankStatements\Actions\DetermineChainStatusAction;
use App\Filament\Admin\Resources\BankStatements\BankStatementResource;
use App\Filament\Admin\Resources\BankStatements\RelationManagers\BankStatementTransactionsRelationManager;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;
use Override;

final class ViewBankStatement extends ViewRecord
{
    #[Override]
    protected static string $resource = BankStatementResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                DetermineChainStatusAction::make(),
            ]),
        ];
    }

    #[Override]
    public function getRelationManagers(): array
    {
        return [
            BankStatementTransactionsRelationManager::class,
        ];
    }

    #[Override]
    #[On('refresh')]
    public function refresh(): void
    {
        $this->record->refresh();
    }
}
