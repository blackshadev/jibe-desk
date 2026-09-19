<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Pages;

use App\Filament\Admin\Resources\BankTransactions\Actions\CompleteBankTransactionAction;
use App\Filament\Admin\Resources\BankTransactions\Actions\LinkReversalAction;
use App\Filament\Admin\Resources\BankTransactions\Actions\RetryMatchingAction;
use App\Filament\Admin\Resources\BankTransactions\Actions\UnlinkReversalAction;
use App\Filament\Admin\Resources\BankTransactions\BankTransactionResource;
use App\Filament\Admin\Resources\BankTransactions\RelationManagers\BookkeepingRecordsRelationManager;
use App\Filament\Admin\Resources\BankTransactions\RelationManagers\InvoicesRelationManager;
use App\Filament\Admin\Resources\BankTransactions\RelationManagers\PurchaseOrdersRelationManager;
use App\Filament\Admin\Resources\BankTransactions\Widgets\BankTransactionStats;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;
use Override;

final class ViewBankTransaction extends ViewRecord
{
    #[Override]
    protected static string $resource = BankTransactionResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            RetryMatchingAction::make(),
            LinkReversalAction::make(),
            UnlinkReversalAction::make(),
            CompleteBankTransactionAction::make(),
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
        return __('labels.bank_transaction');
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
            BankTransactionStats::class,
        ];
    }
}
