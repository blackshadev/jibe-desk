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
use Override;

final class OpenPurchaseOrdersWidget extends StatsOverviewWidget
{
    use VisibleForRoles;

    #[Override]
    protected static ?int $sort = 11;

    #[Override]
    protected ?string $pollingInterval = null;

    /**
     * @var array<string, int>
     */
    #[Override]
    protected int|string|array $columnSpan = [
        'default' => 3,
        'lg' => 1,
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
                    'filters' => [
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
