<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Pages;

use App\Filament\Admin\Resources\Invoices\Actions\CreateCreditAction;
use App\Filament\Admin\Resources\Invoices\Actions\InvoiceStateActions;
use App\Filament\Admin\Resources\Invoices\Actions\ResendInvoiceEmailAction;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Resources\Invoices\RelationManagers\InvoiceBankTransactionsRelationManager;
use App\Filament\Admin\Resources\Invoices\RelationManagers\InvoiceBookkeepingRecordsRelationManager;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;
use Override;

final class EditInvoice extends EditRecord
{
    #[Override]
    protected static string $resource = InvoiceResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ...InvoiceStateActions::make(),
                ResendInvoiceEmailAction::make(),
                CreateCreditAction::make(),
                DeleteAction::make(),
            ])
                ->button()
                ->color('gray'),
        ];
    }

    #[Override]
    protected function getAllRelationManagers(): array
    {
        return [
            InvoiceBankTransactionsRelationManager::class,
            InvoiceBookkeepingRecordsRelationManager::class,
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
        return __('labels.invoice');
    }

    #[On('refresh')]
    #[Override]
    public function refresh(): void
    {
    }
}
