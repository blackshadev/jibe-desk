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
