<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\RelationManagers;

use App\Domain\Invoices\InvoiceStatus;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $relatedResource = InvoiceResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label(__('labels.invoice_number')),
                TextColumn::make('date')
                    ->label(__('labels.invoice_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->formatStateUsing(static fn (InvoiceStatus $state) => __('labels.invoice_status.' . $state->value)),
                TextColumn::make('total')
                    ->label(__('labels.total'))
                    ->alignEnd(),
            ])
            ->recordUrl(ViewOrEdit::route(InvoiceResource::class))
            ->headerActions([
                CreateAction::make(),
            ]);
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.invoice'));
    }

    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.invoices'));
    }
}
