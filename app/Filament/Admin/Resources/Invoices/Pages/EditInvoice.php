<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Pages;

use App\Filament\Admin\Resources\Invoices\Actions\CreateCreditAction;
use App\Filament\Admin\Resources\Invoices\Actions\MarkAsDeclinedAction;
use App\Filament\Admin\Resources\Invoices\Actions\MarkAsPaidAction;
use App\Filament\Admin\Resources\Invoices\Actions\MarkAsPendingAction;
use App\Filament\Admin\Resources\Invoices\Actions\ResendInvoiceEmailAction;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Resources\Invoices\RelationManagers\InvoiceBankTransactionsRelationManager;
use App\Filament\Admin\Resources\Invoices\RelationManagers\InvoiceBookkeepingRecordsRelationManager;
use App\Filament\Admin\Utils\ResourceRouteHelper;
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
                MarkAsPendingAction::make()
                    ->successRedirectUrl(ResourceRouteHelper::view(InvoiceResource::class)),
                MarkAsPaidAction::make()
                    ->successRedirectUrl(ResourceRouteHelper::view(InvoiceResource::class)),
                MarkAsDeclinedAction::make()
                    ->successRedirectUrl(ResourceRouteHelper::view(InvoiceResource::class)),
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
