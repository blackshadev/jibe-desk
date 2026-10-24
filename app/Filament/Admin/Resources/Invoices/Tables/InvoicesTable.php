<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Tables;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceService;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\SharedKernel\CompoundPrice;
use App\Filament\Admin\Resources\Invoices\Actions\InvoiceStateActions;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Utils\ResourceRouteHelper;
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
                SelectFilter::make('book_year')
                    ->label(__('labels.book_year'))
                    ->options(array_merge(
                        ['all' => __('labels.all_years')],
                        Invoice::query()
                            ->distinct()
                            ->select(
                                DB::connection()->getConfig()['driver'] === 'pgsql'
                                    ? DB::raw('EXTRACT(YEAR FROM date) AS year')
                                    : DB::raw('STRFTIME(\'%Y\', date) AS year'),
                            )
                            ->pluck('year', 'year')
                            ->all(),
                    ))
                    ->default(now()->year)
                    ->query(static function (Builder $query, array $state) {
                        $value = $state['value'] ?? '';

                        if ($value === '' || $value === 'all') {
                            return $query;
                        }

                        return $query->whereYear('date', $value);
                    }),
                SelectFilter::make('status')
                    ->label(__('labels.status'))
                    ->options([
                        'outstanding' => __('labels.outstanding'),
                        InvoiceStatus::Open->value => __('labels.invoice_status.open'),
                        InvoiceStatus::Pending->value => __('labels.invoice_status.pending'),
                        InvoiceStatus::Paid->value => __('labels.invoice_status.paid'),
                        InvoiceStatus::Declined->value => __('labels.invoice_status.declined'),
                    ])
                    ->query(static function (Builder $query, array $state) {
                        $value = $state['value'] ?? '';

                        return match ($value) {
                            'outstanding' => $query->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Pending]),
                            '' => $query,
                            default => $query->where('status', $value),
                        };
                    }),
            ])
            ->filtersLayout(FiltersLayout::BeforeContent)
            ->recordUrl(ResourceRouteHelper::viewOrEdit(InvoiceResource::class))
            ->recordActions([
                ActionGroup::make([
                    ...InvoiceStateActions::make(),

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
