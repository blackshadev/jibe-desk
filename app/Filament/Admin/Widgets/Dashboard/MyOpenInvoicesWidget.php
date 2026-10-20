<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\SharedKernel\CompoundPrice;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class MyOpenInvoicesWidget extends TableWidget
{
    #[Override]
    protected static ?int $sort = 20;

    /**
     * @var array<string, int>
     */
    #[Override]
    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 4,
    ];

    #[Override]
    public static function canView(): bool
    {
        return auth()->user()?->isMember() ?? false;
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->heading(__('labels.my_open_invoices'))
            ->query(
                static fn (): Builder => Invoice::query()
                    ->openOrPending()
                    ->whereIn('member_id', auth()->user()?->member?->visibleMemberIds() ?? [])
                    ->orderBy('date'),
            )
            ->columns([
                TextColumn::make('date')
                    ->label(__('labels.invoice_date'))
                    ->date(),
                TextColumn::make('invoice_number')
                    ->label(__('labels.invoice_number')),
                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->formatStateUsing(static fn (InvoiceStatus $state): string => __('labels.invoice_status.' . $state->value)),
                TextColumn::make('total')
                    ->label(__('labels.total'))
                    ->formatStateUsing(static fn (CompoundPrice $state): string => (string) $state)
                    ->alignEnd(),
            ])
            ->recordUrl(
                static fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record]),
            )
            ->emptyStateHeading(__('labels.no_open_invoices'))
            ->paginated(false);
    }
}
