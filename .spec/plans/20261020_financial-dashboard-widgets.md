# Financial dashboard widgets

Date: 2026-10-20
Status: ready for implementation
Depends on: `20261020_dashboard-role-aware-widget-foundation.md`

## Overview

This plan adds the financial-administration widgets to the dashboard and the two drill-down entry points that must land with them:

- open invoices,
- open purchase orders,
- overdue invoice aging (1–29, 30–59, 60+ days),
- SEPA batches needing attention (Open and Pending),
- unresolved bank transactions,
- inventory write-offs within three months.

All six widgets are visible only to `RoleName::FinancialAdministration`. "Outstanding" means `Open` + `Pending`; amounts are excl. VAT and are summed from the line tables, matching `Invoice::total` and `PurchaseOrder::total`.

## Current situation

- `Invoice` has an `openOrPending` scope (`app/Models/Invoice.php`), a `total` accessor built from `invoice_lines.price * quantity`, and a `date` column used as the invoice date.
- `PurchaseOrder` has a `pending` scope but no combined scope; `purchase_order_lines.price` has no quantity.
- `SepaBatchStatus` is `Open`, `Pending`, `Completed`; `SepaBatch` does not store a denormalised total.
- `BankTransaction` has `BankTransactionStatus::Open` and `ResolveStatus::Unresolved`.
- `InventoryItem` exposes `date`, `write_off_period_years`, and a `residual_value` accessor.
- Both list pages already have status tabs, but the invoices table has no status filter and `book_year` defaults to the current year. A drill-down to outstanding invoices must therefore override the year filter or it will hide older debt.
- Money is formatted with `App\Domain\SharedKernel\PriceFormatter`.
- The financial cluster and its resources already gate on `RoleName::FinancialAdministration`.

## Planned changes

- Six widget classes under `app/Filament/Admin/Widgets/Dashboard/`.
- A combined "outstanding" status filter on the invoices table and an `outstanding` option on the purchase-order status filter.
- An `all` option on both `book_year` filters so drill-down links are not limited to the current year.
- Dutch labels in `lang/nl/labels.php`.
- Widget tests covering counts, amounts, role gating, and filter behaviour.

## Open invoices widget

Create `app/Filament/Admin/Widgets/Dashboard/OpenInvoicesWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Domain\SharedKernel\PriceFormatter;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Widgets\Dashboard\Concerns\VisibleForRoles;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Override;

final class OpenInvoicesWidget extends StatsOverviewWidget
{
    use VisibleForRoles;

    #[Override]
    protected static ?int $sort = 10;

    #[Override]
    protected ?string $pollingInterval = null;

    /**
     * @return list<RoleName>
     */
    protected static function roles(): array
    {
        return [RoleName::FinancialAdministration];
    }

    #[Override]
    protected function getStats(): array
    {
        $outstanding = Invoice::query()->openOrPending();

        $count = (clone $outstanding)->count();
        $amount = (float) InvoiceLine::query()
            ->whereIn('invoice_id', (clone $outstanding)->select('invoices.id'))
            ->sum(DB::raw('price * quantity'));

        return [
            Stat::make(__('labels.open_invoices'), (string) $count)
                ->description(__('labels.outstanding_amount') . ': ' . PriceFormatter::format($amount))
                ->chart($this->monthlyInvoicedAmounts())
                ->color('primary')
                ->url(InvoiceResource::getUrl('index', [
                    'tableFilters' => [
                        'book_year' => ['value' => 'all'],
                        'status' => ['value' => 'outstanding'],
                    ],
                ])),
        ];
    }

    /**
     * Invoice status is updated in place, so historical outstanding cannot be
     * reconstructed. The trend therefore shows the amount invoiced per month.
     *
     * @return list<float>
     */
    private function monthlyInvoicedAmounts(): array
    {
        return array_map(
            static function (int $monthsAgo): float {
                $month = CarbonImmutable::now()->startOfMonth()->subMonths($monthsAgo);

                return (float) InvoiceLine::query()
                    ->whereHas('invoice', static fn (Builder $query): Builder => $query
                        ->whereYear('date', $month->year)
                        ->whereMonth('date', $month->month))
                    ->sum(DB::raw('price * quantity'));
            },
            range(11, 0),
        );
    }
}
```

## Open purchase orders widget

Create `app/Filament/Admin/Widgets/Dashboard/OpenPurchaseOrdersWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Domain\SharedKernel\PriceFormatter;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Admin\Widgets\Dashboard\Concerns\VisibleForRoles;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Override;

final class OpenPurchaseOrdersWidget extends StatsOverviewWidget
{
    use VisibleForRoles;

    #[Override]
    protected static ?int $sort = 11;

    #[Override]
    protected ?string $pollingInterval = null;

    /**
     * @return list<RoleName>
     */
    protected static function roles(): array
    {
        return [RoleName::FinancialAdministration];
    }

    #[Override]
    protected function getStats(): array
    {
        $outstanding = PurchaseOrder::query()->whereIn('status', [
            PurchaseOrderStatus::Open,
            PurchaseOrderStatus::Pending,
        ]);

        $count = (clone $outstanding)->count();
        $amount = (float) PurchaseOrderLine::query()
            ->whereIn('purchase_order_id', (clone $outstanding)->select('purchase_orders.id'))
            ->sum('price');

        return [
            Stat::make(__('labels.open_purchase_orders'), (string) $count)
                ->description(__('labels.outstanding_amount') . ': ' . PriceFormatter::format($amount))
                ->chart($this->monthlyPurchaseOrderAmounts())
                ->color('primary')
                ->url(PurchaseOrderResource::getUrl('index', [
                    'tableFilters' => [
                        'book_year' => ['value' => 'all'],
                        'status' => ['value' => 'outstanding'],
                    ],
                ])),
        ];
    }

    /**
     * @return list<float>
     */
    private function monthlyPurchaseOrderAmounts(): array
    {
        return array_map(
            static function (int $monthsAgo): float {
                $month = CarbonImmutable::now()->startOfMonth()->subMonths($monthsAgo);

                return (float) PurchaseOrderLine::query()
                    ->whereHas('purchaseOrder', static fn (Builder $query): Builder => $query
                        ->whereYear('date', $month->year)
                        ->whereMonth('date', $month->month))
                    ->sum('price');
            },
            range(11, 0),
        );
    }
}
```

## Overdue invoices widget

Create `app/Filament/Admin/Widgets/Dashboard/OverdueInvoicesWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Domain\SharedKernel\PriceFormatter;
use App\Filament\Admin\Widgets\Dashboard\Concerns\VisibleForRoles;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Override;

final class OverdueInvoicesWidget extends StatsOverviewWidget
{
    use VisibleForRoles;

    #[Override]
    protected static ?int $sort = 12;

    #[Override]
    protected ?string $pollingInterval = null;

    /**
     * @return list<RoleName>
     */
    protected static function roles(): array
    {
        return [RoleName::FinancialAdministration];
    }

    #[Override]
    protected function getStats(): array
    {
        $today = CarbonImmutable::today();

        return [
            $this->overdueStat(__('labels.overdue_1_29'), $today->subDays(1), $today->subDays(29)),
            $this->overdueStat(__('labels.overdue_30_59'), $today->subDays(30), $today->subDays(59)),
            $this->overdueStat(__('labels.overdue_60_plus'), $today->subDays(60), null),
        ];
    }

    private function overdueStat(string $label, CarbonImmutable $newest, ?CarbonImmutable $oldest): Stat
    {
        $query = Invoice::query()
            ->openOrPending()
            ->where('date', '<=', $newest);

        if ($oldest instanceof CarbonImmutable) {
            $query->where('date', '>=', $oldest);
        }

        $count = (clone $query)->count();

        $amount = (float) InvoiceLine::query()
            ->whereIn('invoice_id', (clone $query)->select('invoices.id'))
            ->sum(DB::raw('price * quantity'));

        return Stat::make($label, (string) $count)
            ->description(PriceFormatter::format($amount))
            ->color($count > 0 ? 'warning' : 'success');
    }
}
```

Invoice dates of today (0 days old) are excluded because the 1–29 bucket ends at yesterday.

## SEPA batches widget

Create `app/Filament/Admin/Widgets/Dashboard/SepaBatchAttentionWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Filament\Admin\Widgets\Dashboard\Concerns\VisibleForRoles;
use App\Models\SepaBatch;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class SepaBatchAttentionWidget extends StatsOverviewWidget
{
    use VisibleForRoles;

    #[Override]
    protected static ?int $sort = 13;

    #[Override]
    protected ?string $pollingInterval = null;

    /**
     * @return list<RoleName>
     */
    protected static function roles(): array
    {
        return [RoleName::FinancialAdministration];
    }

    #[Override]
    protected function getStats(): array
    {
        return [
            Stat::make(
                __('labels.sepa_batches_open'),
                (string) SepaBatch::query()->where('status', SepaBatchStatus::Open)->count(),
            )
                ->description(__('labels.sepa_batches_open_description'))
                ->color('warning'),
            Stat::make(
                __('labels.sepa_batches_pending'),
                (string) SepaBatch::query()->where('status', SepaBatchStatus::Pending)->count(),
            )
                ->description(__('labels.sepa_batches_pending_description'))
                ->color('primary'),
        ];
    }
}
```

`SepaBatch::total` loads all invoices and purchase orders and is not used here: the widget answers "how many batches need attention", which is a count, not a balance.

## Unresolved bank transactions widget

Create `app/Filament/Admin/Widgets/Dashboard/UnresolvedBankTransactionsWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Domain\Bookkeeping\Banking\BankTransactions\BankTransactionStatus;
use App\Domain\Bookkeeping\Banking\BankTransactions\ResolveStatus;
use App\Domain\SharedKernel\PriceFormatter;
use App\Filament\Admin\Widgets\Dashboard\Concerns\VisibleForRoles;
use App\Models\BankTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class UnresolvedBankTransactionsWidget extends StatsOverviewWidget
{
    use VisibleForRoles;

    #[Override]
    protected static ?int $sort = 14;

    #[Override]
    protected ?string $pollingInterval = null;

    /**
     * @return list<RoleName>
     */
    protected static function roles(): array
    {
        return [RoleName::FinancialAdministration];
    }

    #[Override]
    protected function getStats(): array
    {
        $query = BankTransaction::query()
            ->where('status', BankTransactionStatus::Open)
            ->where('resolve_status', ResolveStatus::Unresolved);

        return [
            Stat::make(__('labels.unresolved_bank_transactions'), (string) (clone $query)->count())
                ->description(PriceFormatter::format((float) (clone $query)->sum('amount')))
                ->color('warning'),
        ];
    }
}
```

`matched_amount` and `unmatched_amount` are per-model accessors that issue several queries each. The widget sums the transaction amount over the same filtered set instead, so it stays a single aggregate query.

## Inventory write-offs widget

Create `app/Filament/Admin/Widgets/Dashboard/InventoryWriteOffWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Domain\SharedKernel\PriceFormatter;
use App\Filament\Admin\Widgets\Dashboard\Concerns\VisibleForRoles;
use App\Models\InventoryItem;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class InventoryWriteOffWidget extends StatsOverviewWidget
{
    use VisibleForRoles;

    #[Override]
    protected static ?int $sort = 15;

    #[Override]
    protected ?string $pollingInterval = null;

    /**
     * @return list<RoleName>
     */
    protected static function roles(): array
    {
        return [RoleName::FinancialAdministration];
    }

    #[Override]
    protected function getStats(): array
    {
        $today = CarbonImmutable::today();
        $cutoff = $today->addMonths(3);

        $items = InventoryItem::query()
            ->get()
            ->filter(static function (InventoryItem $item) use ($today, $cutoff): bool {
                $endOfWriteOff = $item->date->toImmutable()->addYears($item->write_off_period_years);

                return $endOfWriteOff->greaterThanOrEqualTo($today)
                    && $endOfWriteOff->lessThanOrEqualTo($cutoff);
            });

        return [
            Stat::make(__('labels.inventory_write_offs'), (string) $items->count())
                ->description(__('labels.residual_value') . ': ' . PriceFormatter::format((float) $items->sum('residual_value')))
                ->color($items->isEmpty() ? 'success' : 'warning'),
        ];
    }
}
```

The inventory table is small, and `residual_value` is a PHP accessor, so filtering in PHP avoids database-specific date arithmetic while keeping the widget to a single query.

## Registering the widgets

Append the six classes to `DashboardWidgets::all()`:

```php
public static function all(): array
{
    return [
        MemberOverview::class,
        HouseholdMembersWidget::class,
        OpenInvoicesWidget::class,
        OpenPurchaseOrdersWidget::class,
        OverdueInvoicesWidget::class,
        SepaBatchAttentionWidget::class,
        UnresolvedBankTransactionsWidget::class,
        InventoryWriteOffWidget::class,
    ];
}
```

## Drill-down filters

### Invoices table

Update `app/Filament/Admin/Resources/Invoices/Tables/InvoicesTable.php`:

- add an `all` option to the `book_year` filter and skip the year constraint for it,
- add a status filter with a virtual `outstanding` value.

```php
SelectFilter::make(__('labels.book_year'))
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
SelectFilter::make(__('labels.status'))
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
```

### Purchase orders table

Update `app/Filament/Admin/Resources/PurchaseOrders/Tables/PurchaseOrdersTable.php`:

- add the same `all` option to `book_year`,
- add `outstanding` to the existing status filter and map it to `whereIn([Open, Pending])`,
- include `PurchaseOrderStatus::Cancelled` in the options, since the column can now contain it.

```php
SelectFilter::make(__('labels.book_year'))
    ->options(array_merge(
        ['all' => __('labels.all_years')],
        PurchaseOrder::query()
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
```

```php
->options([
    'outstanding' => __('labels.outstanding'),
    PurchaseOrderStatus::Open->value => __('labels.purchase_order_status.open'),
    PurchaseOrderStatus::Pending->value => __('labels.purchase_order_status.pending'),
    PurchaseOrderStatus::Declined->value => __('labels.purchase_order_status.declined'),
    PurchaseOrderStatus::Paid->value => __('labels.purchase_order_status.paid'),
    PurchaseOrderStatus::Cancelled->value => __('labels.purchase_order_status.cancelled'),
])
->query(static function (Builder $query, array $state) {
    $value = $state['value'] ?? '';

    return match ($value) {
        'outstanding' => $query->whereIn('status', [PurchaseOrderStatus::Open, PurchaseOrderStatus::Pending]),
        '' => $query,
        default => $query->where('status', $value),
    };
}),
```

## Labels

Add to `lang/nl/labels.php` (near the existing invoice and SEPA labels):

```php
'open_invoices' => 'Openstaande facturen',
'open_purchase_orders' => 'Openstaande declaraties',
'outstanding' => 'Openstaand',
'outstanding_amount' => 'Openstaand bedrag',
'all_years' => 'Alle jaren',
'overdue_1_29' => '1-29 dagen te laat',
'overdue_30_59' => '30-59 dagen te laat',
'overdue_60_plus' => '60+ dagen te laat',
'sepa_batches_open' => 'SEPA rondes in opbouw',
'sepa_batches_open_description' => 'Nog niet klaar voor export',
'sepa_batches_pending' => 'SEPA rondes klaar voor export',
'sepa_batches_pending_description' => 'Wachten op export naar de bank',
'unresolved_bank_transactions' => 'Onverwerkte banktransacties',
'inventory_write_offs' => 'Afschrijvingen binnen 3 maanden',
```

`labels.residual_value` already exists and is reused.

## Tests

Create `tests/Feature/Filament/Dashboard/OpenInvoicesWidgetTest.php` with:

- two Open invoices and one Pending invoice in the current and previous year; assert the stat shows `3`;
- assert the formatted outstanding amount appears;
- assert a Paid and a Declined invoice are excluded;
- assert `OpenInvoicesWidget::canView()` is true for `financial_administration` and false for `member_administration` and members.

```php
public function test_it_counts_open_and_pending_invoices_and_sums_their_lines(): void
{
    $this->withUserHavingRole(RoleName::FinancialAdministration);

    Invoice::factory()->createQuietly(['status' => InvoiceStatus::Open])
        ->lines()->save(InvoiceLine::factory()->createQuietly(['price' => 100.0, 'quantity' => 2]));
    Invoice::factory()->createQuietly(['status' => InvoiceStatus::Pending])
        ->lines()->save(InvoiceLine::factory()->createQuietly(['price' => 50.0, 'quantity' => 1]));
    Invoice::factory()->createQuietly(['status' => InvoiceStatus::Paid])
        ->lines()->save(InvoiceLine::factory()->createQuietly(['price' => 999.0, 'quantity' => 1]));

    Livewire::test(OpenInvoicesWidget::class)
        ->assertSee('2')
        ->assertSee(PriceFormatter::format(250.0));
}
```

Create similar focused tests:

- `tests/Feature/Filament/Dashboard/OpenPurchaseOrdersWidgetTest.php` — Open and Pending counted, Paid and Declined excluded, amount summed from `purchase_order_lines.price`.
- `tests/Feature/Filament/Dashboard/OverdueInvoicesWidgetTest.php` — invoices dated 5, 35, and 70 days ago land in the three buckets; an invoice from today and a Paid invoice are excluded.
- `tests/Feature/Filament/Dashboard/SepaBatchAttentionWidgetTest.php` — one Open batch, two Pending, one Completed; assert `1` and `2` appear and the Completed batch is excluded.
- `tests/Feature/Filament/Dashboard/UnresolvedBankTransactionsWidgetTest.php` — unresolved open transactions counted, resolved and completed transactions excluded, amount summed.
- `tests/Feature/Filament/Dashboard/InventoryWriteOffWidgetTest.php` — an item ending in one month is counted, one ending in six months and one already fully written off are not.
- `tests/Feature/Filament/Invoices/InvoiceStatusFilterTest.php` — selecting `outstanding` shows Open and Pending rows only; selecting `all` for `book_year` shows rows from previous years.

Use `tests\Concerns\WithAuthorizedUser` and `FeatureTestCase` throughout. Run with:

```bash
./Taskfile artisan test --compact tests/Feature/Filament/Dashboard
./Taskfile artisan test --compact tests/Feature/Filament/Invoices/InvoiceStatusFilterTest.php
```
