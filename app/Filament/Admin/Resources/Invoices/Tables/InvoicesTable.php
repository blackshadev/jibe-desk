<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Tables;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\Invoices\InvoiceService;
use App\Domain\Invoices\InvoiceStatus;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.name')
                    ->label(__('labels.member'))
                    ->searchable(),
                TextColumn::make('date')
                    ->label(__('labels.invoice_date'))
                    ->sortable()
                    ->date(),
                TextColumn::make('invoice_number')
                    ->label(__('labels.invoice_number'))
                    ->searchable(),
                TextColumn::make('date')
                    ->label(__('labels.invoice_date'))
                    ->date(),
                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->formatStateUsing(static fn (InvoiceStatus $state) => __('labels.invoice_status.' . $state->value)),
                TextColumn::make('total')
                    ->label(__('labels.total'))
                    ->formatStateUsing(static fn (CompoundPrice $state) => (string) $state)
                    ->alignEnd(),
                TextColumn::make('created_at')
                    ->label(__('labels.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('labels.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make(__('labels.book_year'))
                    ->options(
                        Invoice::query()
                            ->distinct()
                            ->select(
                                DB::connection()->getConfig()['driver'] === 'pgsql'
                                    ? DB::raw('EXTRACT(YEAR FROM date) AS year')
                                    : DB::raw('STRFTIME(\'%Y\', date) AS year'),
                            )
                            ->pluck('year', 'year')
                            ->all(),
                    )
                    ->default(now()->year)
                    ->query(static function (Builder $query, array $state) {
                        $value = $state['value'] ?? '';
                        if ($value === '') {
                            return $query;
                        }

                        return $query->whereYear('date', $value);
                    }),
            ])
            ->filtersLayout(FiltersLayout::BeforeContent)
            ->recordUrl(ViewOrEdit::route(InvoiceResource::class))
            ->recordActions([
                ActionGroup::make([
                    Action::make('markAsPending')
                        ->label(__('labels.mark_as_pending'))
                        ->icon('heroicon-m-clock')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalDescription(__('labels.manual_mark_pending_warning'))
                        ->visible(static fn (Invoice $record) => auth()->user()?->can('mark-pending', $record) ?? false)
                        ->action(static function (Invoice $record, InvoiceService $invoiceService): void {
                            $invoiceService->markAsPending(new InvoiceIdList([InvoiceId::create($record->id)]));
                        })
                        ->successNotificationTitle(__('notifications.invoice_status_updated')),

                    Action::make('markAsPaid')
                        ->label(__('labels.mark_as_paid'))
                        ->icon('heroicon-m-banknotes')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription(__('labels.manual_mark_paid_warning'))
                        ->visible(static fn (Invoice $record) => auth()->user()?->can('mark-paid', $record) ?? false)
                        ->action(static function (Invoice $record, InvoiceService $invoiceService) {
                            $invoiceService->markAsPaid(new InvoiceIdList([InvoiceId::create($record->id)]));
                        })
                        ->successNotificationTitle(__('notifications.invoice_status_updated')),

                    Action::make('markAsDeclined')
                        ->label(__('labels.mark_as_declined'))
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('labels.manual_mark_declined_warning'))
                        ->visible(static fn (Invoice $record) => auth()->user()?->can('mark-declined', $record) ?? false)
                        ->action(static function (Invoice $record, InvoiceService $invoiceService) {
                            $invoiceService->markAsDeclined(new InvoiceIdList([InvoiceId::create($record->id)]));
                        })
                        ->successNotificationTitle(__('notifications.invoice_status_updated')),

                    Action::make('createCredit')
                        ->label(__('labels.create_credit_invoice'))
                        ->icon('heroicon-m-arrow-uturn-left')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription(__('labels.create_credit_invoice_warning'))
                        ->visible(static fn (Invoice $record) => auth()->user()?->can('create-credit', $record) ?? false)
                        ->action(static function (Invoice $record, InvoiceService $invoiceService): void {
                            $invoiceService->createCredit(InvoiceId::create($record->id));
                        })
                        ->successNotificationTitle(__('notifications.credit_invoice_created')),
                ]),
            ]);
    }
}
