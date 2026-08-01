<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\Invoices\InvoiceService;
use App\Domain\Invoices\InvoiceStatus;
use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Admin\Resources\Invoices\Pages\ViewInvoice;
use App\Models\BookkeepingRecord;
use App\Models\Invoice;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class InvoiceResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_mark_as_paid_creates_bookkeeping_records(): void
    {
        $this->withAuthorizedUser();
        $invoice = Invoice::factory()->withLines(1)->createQuietly(['status' => InvoiceStatus::Pending, 'date' => '2026-01-15']);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertSuccessful()
            ->callAction('markAsPaid');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Paid->value,
        ]);

        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);
    }

    public function test_mark_as_declined_does_not_create_bookkeeping_records(): void
    {
        $this->withAuthorizedUser();
        $invoice = Invoice::factory()->withLines(1)->createQuietly(['status' => InvoiceStatus::Pending]);

        $bookkeepingCountBefore = BookkeepingRecord::query()->count();

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertSuccessful()
            ->callAction('markAsDeclined');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Declined->value,
        ]);

        $bookkeepingCountAfter = BookkeepingRecord::query()->count();

        static::assertSame($bookkeepingCountBefore, $bookkeepingCountAfter);
    }

    public function test_bulk_mark_as_paid_updates_multiple_invoices(): void
    {
        $invoices = Invoice::factory()->withLines(1)->count(3)->createQuietly(['status' => InvoiceStatus::Pending]);

        $ids = array_map(
            static fn (Invoice $invoice) => InvoiceId::create($invoice->id),
            $invoices->all(),
        );

        $service = app(InvoiceService::class);
        $service->markAsPaid(new InvoiceIdList($ids));

        foreach ($invoices as $invoice) {
            $this->assertDatabaseHas('invoices', [
                'id' => $invoice->id,
                'status' => InvoiceStatus::Paid->value,
            ]);
        }
    }

    public function test_bulk_mark_as_declined_updates_multiple_invoices(): void
    {
        $invoices = Invoice::factory()->withLines(1)->count(3)->createQuietly(['status' => InvoiceStatus::Pending]);

        $ids = array_map(
            static fn (Invoice $invoice) => InvoiceId::create($invoice->id),
            $invoices->all(),
        );

        $service = app(InvoiceService::class);
        $service->markAsDeclined(new InvoiceIdList($ids));

        foreach ($invoices as $invoice) {
            $this->assertDatabaseHas('invoices', [
                'id' => $invoice->id,
                'status' => InvoiceStatus::Declined->value,
            ]);
        }
    }

    public function test_mark_as_declined_only_affects_pending_invoices(): void
    {
        $pendingInvoice = Invoice::factory()->withLines(1)->createQuietly(['status' => InvoiceStatus::Pending]);
        $paidInvoice = Invoice::factory()->withLines(1)->createQuietly(['status' => InvoiceStatus::Paid]);

        $service = app(InvoiceService::class);
        $service->markAsDeclined(new InvoiceIdList([
            InvoiceId::create($pendingInvoice->id),
            InvoiceId::create($paidInvoice->id),
        ]));

        $this->assertDatabaseHas('invoices', [
            'id' => $pendingInvoice->id,
            'status' => InvoiceStatus::Declined->value,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $paidInvoice->id,
            'status' => InvoiceStatus::Paid->value,
        ]);
    }

    public function test_list_page_renders(): void
    {
        $this->withAuthorizedUser();
        Invoice::factory()->withLines(1)->createQuietly(['status' => InvoiceStatus::Pending]);

        Livewire::test(ListInvoices::class)
            ->assertSuccessful();
    }
}
