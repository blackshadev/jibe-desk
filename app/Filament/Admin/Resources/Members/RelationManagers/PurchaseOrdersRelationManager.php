<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\RelationManagers;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use App\Models\PurchaseOrder;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;

final class PurchaseOrdersRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'purchaseOrders';

    #[Override]
    protected static ?string $relatedResource = PurchaseOrderResource::class;

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label(__('labels.date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->limit(50),
                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->formatStateUsing(static fn (PurchaseOrderStatus $state) => __('labels.purchase_order_status.' . $state->value))
                    ->icon(static fn (PurchaseOrderStatus $state) => match ($state) {
                        PurchaseOrderStatus::Pending => Heroicon::Clock,
                        PurchaseOrderStatus::Declined => Heroicon::XCircle,
                        PurchaseOrderStatus::Paid => Heroicon::CheckCircle,
                        PurchaseOrderStatus::Open => Heroicon::DocumentText,
                    })
                    ->tooltip(static fn (PurchaseOrder $record): ?string => $record->declined_reason),
                TextColumn::make('total')
                    ->label(__('labels.total'))
                    ->formatStateUsing(static fn (CompoundPrice $state) => (string) $state)
                    ->alignEnd(),
            ])
            ->recordUrl(ViewOrEdit::route(PurchaseOrderResource::class))
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->url(static fn (RelationManager $livewire): string => PurchaseOrderResource::getUrl('create', [
                        'member_id' => $livewire->getOwnerRecord()->getKey(),
                    ])),
            ]);
    }

    #[Override]
    public function isReadOnly(): bool
    {
        return false;
    }

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.purchase_orders');
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.purchase_order'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.purchase_orders'));
    }
}
