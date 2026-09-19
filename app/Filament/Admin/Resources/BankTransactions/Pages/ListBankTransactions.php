<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Pages;

use App\Filament\Admin\Actions\ImportMt940Action;
use App\Filament\Admin\Resources\BankTransactions\BankTransactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs;
use Override;

final class ListBankTransactions extends ListRecords
{
    #[Override]
    protected static string $resource = BankTransactionResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ImportMt940Action::make(),
            CreateAction::make(),
        ];
    }

    #[Override]
    public function getTabs(): array
    {
        return [
            'all' => Tabs\Tab::make(__('labels.all')),
            'open' => Tabs\Tab::make(__('labels.open'))->modifyQueryUsing(static fn ($query) => $query->where('status', 'open')),
            'completed' => Tabs\Tab::make(__('labels.completed'))->modifyQueryUsing(static fn ($query) => $query->where('status', 'completed')),
            'unresolvable' => Tabs\Tab::make(__('labels.resolve_status_unresolvable'))->modifyQueryUsing(static fn ($query) => $query->where('resolve_status', 'unresolvable')),
        ];
    }
}
