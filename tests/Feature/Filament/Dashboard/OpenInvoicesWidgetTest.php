<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\SharedKernel\PriceFormatter;
use App\Filament\Admin\Widgets\Dashboard\OpenInvoicesWidget;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class OpenInvoicesWidgetTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_it_counts_open_and_pending_invoices_and_sums_their_lines(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        Invoice::factory()
            ->createQuietly(['status' => InvoiceStatus::Open, 'date' => now()->subYear()->toDateString()])
            ->lines()
            ->save(InvoiceLine::factory()->make(['price' => 100.0, 'quantity' => 2]));
        Invoice::factory()
            ->createQuietly(['status' => InvoiceStatus::Pending])
            ->lines()
            ->save(InvoiceLine::factory()->make(['price' => 50.0, 'quantity' => 1]));
        Invoice::factory()
            ->createQuietly(['status' => InvoiceStatus::Open])
            ->lines()
            ->save(InvoiceLine::factory()->make(['price' => 10.0, 'quantity' => 1]));
        Invoice::factory()
            ->createQuietly(['status' => InvoiceStatus::Paid])
            ->lines()
            ->save(InvoiceLine::factory()->make(['price' => 999.0, 'quantity' => 1]));
        Invoice::factory()
            ->createQuietly(['status' => InvoiceStatus::Declined])
            ->lines()
            ->save(InvoiceLine::factory()->make(['price' => 999.0, 'quantity' => 1]));

        Livewire::test(OpenInvoicesWidget::class)
            ->assertSee('3')
            ->assertSee(PriceFormatter::format(260.0));
    }

    public function test_it_is_only_visible_for_financial_administration(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);
        static::assertTrue(OpenInvoicesWidget::canView());

        $this->withUserHavingRole(RoleName::MemberAdministration);
        static::assertFalse(OpenInvoicesWidget::canView());

        $this->actingAs(User::factory()->memberUser()->createQuietly());
        static::assertFalse(OpenInvoicesWidget::canView());
    }
}
