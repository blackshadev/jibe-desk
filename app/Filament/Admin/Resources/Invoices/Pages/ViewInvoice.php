<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Pages;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\Invoices\InvoiceService;
use App\Filament\Admin\Resources\Invoices\Actions\CreateCreditAction;
use App\Filament\Admin\Resources\Invoices\Actions\MarkAsDeclinedAction;
use App\Filament\Admin\Resources\Invoices\Actions\MarkAsPaidAction;
use App\Filament\Admin\Resources\Invoices\Actions\MarkAsPendingAction;
use App\Filament\Admin\Resources\Invoices\Actions\ResendInvoiceEmailAction;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Resources\Invoices\RelationManagers\InvoiceBankTransactionsRelationManager;
use App\Filament\Admin\Resources\Invoices\RelationManagers\InvoiceBookkeepingRecordsRelationManager;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;
use Override;

final class ViewInvoice extends ViewRecord
{
    #[Override]
    protected static string $resource = InvoiceResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            MarkAsPendingAction::make(),
            MarkAsPaidAction::make(),
            MarkAsDeclinedAction::make(),
            ResendInvoiceEmailAction::make(),
            CreateCreditAction::make(),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    #[Override]
    #[On('refresh')]
    public function refresh(): void
    {
        $this->record->refresh();
        $this->fillForm();
    }

    #[Override]
    public function getRelationManagers(): array
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
}
