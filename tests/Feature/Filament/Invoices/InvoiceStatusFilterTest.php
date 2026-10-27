<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Invoices;

use App\Domain\Authorization\RoleName;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class InvoiceStatusFilterTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_outstanding_filter_shows_only_open_and_pending(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        $open = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Open, 'date' => now()->toDateString()]);
        $pending = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Pending, 'date' => now()->toDateString()]);
        $paid = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Paid, 'date' => now()->toDateString()]);

        Livewire::test(ListInvoices::class)
            ->filterTable('status', 'outstanding')
            ->assertCanSeeTableRecords([$open, $pending])
            ->assertCanNotSeeTableRecords([$paid]);
    }

    public function test_settled_filter_shows_only_settled(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        $settled = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Settled, 'date' => now()->toDateString()]);
        $pending = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Pending, 'date' => now()->toDateString()]);

        Livewire::test(ListInvoices::class)
            ->filterTable('status', InvoiceStatus::Settled->value)
            ->assertCanSeeTableRecords([$settled])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_settled_tab_shows_only_settled_invoices(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        $settled = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Settled, 'date' => now()->toDateString()]);
        $pending = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Pending, 'date' => now()->toDateString()]);

        Livewire::test(ListInvoices::class)
            ->set('activeTab', 'settled')
            ->assertCanSeeTableRecords([$settled])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_settled_invoice_renders_the_verrekend_label(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        $settled = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Settled, 'date' => now()->toDateString()]);

        Livewire::test(ListInvoices::class)
            ->assertCanSeeTableRecords([$settled])
            ->assertSee('Verrekend');
    }

    public function test_all_book_year_shows_previous_years(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        $current = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Open, 'date' => now()->toDateString()]);
        $previous = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Open, 'date' => now()->subYear()->toDateString()]);

        Livewire::test(ListInvoices::class)
            ->filterTable('book_year', 'all')
            ->assertCanSeeTableRecords([$current, $previous]);
    }
}
