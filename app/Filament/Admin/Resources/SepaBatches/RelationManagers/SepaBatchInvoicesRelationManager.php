<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\RelationManagers;

use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Filament\Admin\Labels\InvoiceStatusLabels;
use App\Filament\Admin\Resources\Invoices\Actions\InvoiceStateActions;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Utils\ResourceRouteHelper;
use App\Models\Invoice;
use App\Models\SepaBatch;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Override;

final class SepaBatchInvoicesRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'invoices';

    #[Override]
    protected static ?string $relatedResource = InvoiceResource::class;

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label(__('labels.invoice_number'))
                    ->searchable(),
                TextColumn::make('recipient_name')
                    ->label(__('labels.recipient_name'))
                    ->searchable(),
                TextColumn::make('total')
                    ->label(__('labels.total'))
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->formatStateUsing(static fn (InvoiceStatus $state) => InvoiceStatusLabels::options()[$state->value]),
            ])
            ->recordUrl(ResourceRouteHelper::viewOrEdit(InvoiceResource::class))
            ->recordActions([
                ActionGroup::make([
                    ...InvoiceStateActions::make(),
                    Action::make('removeFromBatch')
                        ->label(__('labels.remove_from_batch'))
                        ->icon('heroicon-m-minus-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(static function (Invoice $record, RelationManager $livewire): bool {
                            /** @var SepaBatch $batch */
                            $batch = $livewire->getOwnerRecord();

                            return $batch->status === SepaBatchStatus::Open;
                        })
                        ->action(static function (Invoice $record): void {
                            $record->update(['sepa_batch_id' => null]);
                        })
                        ->after(static fn (RelationManager $livewire) => $livewire->dispatch('refreshInvoicesTable')),
                ]),
            ]);
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.invoice'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.invoices'));
    }

    #[Override]
    public static function getBadge(Model $ownerRecord, string $pageClass): string
    {
        return (string) $ownerRecord->{static::$relationship}->count();
    }

    #[On('refreshInvoicesTable')]
    public function refresh(): void
    {
    }
}
