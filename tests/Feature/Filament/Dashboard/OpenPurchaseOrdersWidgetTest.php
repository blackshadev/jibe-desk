<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Domain\SharedKernel\PriceFormatter;
use App\Filament\Admin\Widgets\Dashboard\OpenPurchaseOrdersWidget;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class OpenPurchaseOrdersWidgetTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_it_counts_open_and_pending_orders_and_sums_lines(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        PurchaseOrder::factory()
            ->open()
            ->createQuietly()
            ->lines()
            ->save(PurchaseOrderLine::factory()->make(['price' => 100.0]));
        PurchaseOrder::factory()
            ->pending()
            ->createQuietly()
            ->lines()
            ->save(PurchaseOrderLine::factory()->make(['price' => 50.0]));
        PurchaseOrder::factory()
            ->paid()
            ->createQuietly()
            ->lines()
            ->save(PurchaseOrderLine::factory()->make(['price' => 999.0]));
        PurchaseOrder::factory()
            ->declined()
            ->createQuietly()
            ->lines()
            ->save(PurchaseOrderLine::factory()->make(['price' => 999.0]));

        Livewire::test(OpenPurchaseOrdersWidget::class)
            ->assertSee('2')
            ->assertSee(PriceFormatter::format(150.0));
    }

    public function test_it_is_only_visible_for_financial_administration(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);
        static::assertTrue(OpenPurchaseOrdersWidget::canView());

        $this->withUserHavingRole(RoleName::MemberAdministration);
        static::assertFalse(OpenPurchaseOrdersWidget::canView());
    }
}
