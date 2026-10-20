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
     * @var array<string, int>
     */
    #[Override]
    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 4,
    ];

    /**
     * @return list<RoleName>
     */
    #[Override]
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
                    'filters' => [
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
