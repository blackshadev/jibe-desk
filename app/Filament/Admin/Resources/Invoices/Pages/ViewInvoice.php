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
use Filament\Actions\ActionGroup;
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
    public static function authorizeResourceAccess(): void
    {
        abort_unless(
            static::getResource()::canAccess() || (auth()->user()?->isMember() ?? false),
            403,
        );
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                MarkAsPendingAction::make(),
                MarkAsPaidAction::make(),
                MarkAsDeclinedAction::make(),
                ResendInvoiceEmailAction::make(),
                CreateCreditAction::make(),
                DeleteAction::make(),
            ])
                ->button()
                ->color('gray'),
            EditAction::make(),
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
        return auth()->user()->isAdmin();
    }

    #[Override]
    public function getContentTabLabel(): string
    {
        return __('labels.invoice');
    }
}
