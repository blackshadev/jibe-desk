<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Pages;

use App\Filament\Admin\Resources\BankingTransactions\Actions\CompleteBankingTransactionAction;
use App\Filament\Admin\Resources\BankingTransactions\Actions\LinkReversalAction;
use App\Filament\Admin\Resources\BankingTransactions\Actions\RetryMatchingAction;
use App\Filament\Admin\Resources\BankingTransactions\Actions\UnlinkReversalAction;
use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use App\Filament\Admin\Resources\BankingTransactions\RelationManagers\BookkeepingRecordsRelationManager;
use App\Filament\Admin\Resources\BankingTransactions\RelationManagers\InvoicesRelationManager;
use App\Filament\Admin\Resources\BankingTransactions\RelationManagers\PurchaseOrdersRelationManager;
use App\Filament\Admin\Resources\BankingTransactions\Widgets\BankingTransactionStats;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;
use Override;

final class ViewBankingTransaction extends ViewRecord
{
    #[Override]
    protected static string $resource = BankingTransactionResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            RetryMatchingAction::make(),
            LinkReversalAction::make(),
            UnlinkReversalAction::make(),
            CompleteBankingTransactionAction::make(),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    #[Override]
    public function getRelationManagers(): array
    {
        return [
            InvoicesRelationManager::class,
            PurchaseOrdersRelationManager::class,
            BookkeepingRecordsRelationManager::class,
        ];
    }

    #[Override]
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    #[Override]
    public function getContentTabLabel(): string
    {
        return __('labels.banking_transaction');
    }

    #[Override]
    #[On('refresh')]
    public function refresh(): void
    {
        $this->record->refresh();
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            BankingTransactionStats::class,
        ];
    }
}
