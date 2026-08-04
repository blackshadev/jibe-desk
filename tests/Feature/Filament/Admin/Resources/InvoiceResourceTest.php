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

    public function test_create_credit_action_creates_credit_invoice_for_pending(): void
    {
        $this->withAuthorizedUser();
        $invoice = Invoice::factory()->withLines(2)->createQuietly(['status' => InvoiceStatus::Pending]);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertSuccessful()
            ->callAction('createCredit');

        $this->assertDatabaseHas('invoices', [
            'credit_invoice_id' => $invoice->id,
            'status' => InvoiceStatus::Open->value,
        ]);

        $credit = Invoice::query()->where('credit_invoice_id', $invoice->id)->first();
        static::assertNotNull($credit);
        static::assertSame($invoice->member_id, $credit->member_id);
        static::assertSame($invoice->recipient_email, $credit->recipient_email);
        static::assertSame($invoice->recipient_name, $credit->recipient_name);
        static::assertSame($invoice->recipient_address, $credit->recipient_address);
        static::assertNotSame($invoice->invoice_number, $credit->invoice_number);

        $this->assertDatabaseCount('invoice_lines', 4);
    }

    public function test_create_credit_action_creates_credit_invoice_for_paid(): void
    {
        $this->withAuthorizedUser();
        $invoice = Invoice::factory()->withLines(1)->createQuietly(['status' => InvoiceStatus::Paid]);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertSuccessful()
            ->callAction('createCredit');

        $this->assertDatabaseHas('invoices', [
            'credit_invoice_id' => $invoice->id,
            'status' => InvoiceStatus::Open->value,
        ]);
    }

    public function test_create_credit_action_creates_credit_invoice_for_declined(): void
    {
        $this->withAuthorizedUser();
        $invoice = Invoice::factory()->withLines(1)->createQuietly(['status' => InvoiceStatus::Declined]);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertSuccessful()
            ->callAction('createCredit');

        $this->assertDatabaseHas('invoices', [
            'credit_invoice_id' => $invoice->id,
            'status' => InvoiceStatus::Open->value,
        ]);
    }

    public function test_create_credit_action_not_visible_for_open_invoice(): void
    {
        $this->withAuthorizedUser();
        $invoice = Invoice::factory()->withLines(1)->createQuietly(['status' => InvoiceStatus::Open]);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertSuccessful()
            ->assertActionHidden('createCredit');
    }

    public function test_create_credit_action_not_visible_for_already_credited_invoice(): void
    {
        $this->withAuthorizedUser();
        $invoice = Invoice::factory()->withLines(1)->hasCreditInvoice()->createQuietly(['status' => InvoiceStatus::Pending]);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertSuccessful()
            ->assertActionHidden('createCredit');
    }

    public function test_mark_as_pending_action_marks_open_invoice_as_pending(): void
    {
        $this->withAuthorizedUser();
        $invoice = Invoice::factory()->withLines(1)->createQuietly(['status' => InvoiceStatus::Open]);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertSuccessful()
            ->callAction('markAsPending');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Pending->value,
        ]);
    }

    public function test_mark_as_pending_action_not_visible_for_pending_invoice(): void
    {
        $this->withAuthorizedUser();
        $invoice = Invoice::factory()->withLines(1)->createQuietly(['status' => InvoiceStatus::Pending]);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertSuccessful()
            ->assertActionHidden('markAsPending');
    }

    public function test_mark_as_pending_action_not_visible_for_paid_invoice(): void
    {
        $this->withAuthorizedUser();
        $invoice = Invoice::factory()->withLines(1)->createQuietly(['status' => InvoiceStatus::Paid]);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertSuccessful()
            ->assertActionHidden('markAsPending');
    }

    public function test_bulk_mark_as_pending_updates_multiple_open_invoices(): void
    {
        $this->withAuthorizedUser();
        $invoices = Invoice::factory()->withLines(1)->count(3)->createQuietly(['status' => InvoiceStatus::Open]);

        $ids = array_map(
            static fn (Invoice $invoice) => InvoiceId::create($invoice->id),
            $invoices->all(),
        );

        $service = app(InvoiceService::class);
        $service->markAsPending(new InvoiceIdList($ids));

        foreach ($invoices as $invoice) {
            $this->assertDatabaseHas('invoices', [
                'id' => $invoice->id,
                'status' => InvoiceStatus::Pending->value,
            ]);
        }
    }

    public function test_mark_as_pending_only_affects_open_invoices(): void
    {
        $openInvoice = Invoice::factory()->withLines(1)->createQuietly(['status' => InvoiceStatus::Open]);
        $pendingInvoice = Invoice::factory()->withLines(1)->createQuietly(['status' => InvoiceStatus::Pending]);

        $service = app(InvoiceService::class);
        $service->markAsPending(new InvoiceIdList([
            InvoiceId::create($openInvoice->id),
            InvoiceId::create($pendingInvoice->id),
        ]));

        $this->assertDatabaseHas('invoices', [
            'id' => $openInvoice->id,
            'status' => InvoiceStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $pendingInvoice->id,
            'status' => InvoiceStatus::Pending->value,
        ]);
    }
}
