<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\AppliedInvoiceWithLineIds;
use App\Domain\Invoices\ApplyInvoiceLines;
use App\Domain\Invoices\Billing\BillableItem;
use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemList;
use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceGeneratorImpl;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceLineId;
use App\Domain\Invoices\InvoiceTarget;
use App\Domain\Members\MemberId;
use DateTimeImmutable;
use Tests\UnitTestCase;

final class InvoiceGeneratorImplTest extends UnitTestCase
{
    private BillableItemsViewRepositoryExpectation $billableItemsViewRepository;
    private CreateInvoiceExpectation $invoiceRepository;
    private InvoiceBatchRepositoryExpectation $invoiceBatchRepository;
    private InvoiceGeneratorImpl $subject;

    protected function setup(): void
    {
        parent::setup();

        $this->billableItemsViewRepository = BillableItemsViewRepositoryExpectation::create();
        $this->invoiceRepository = CreateInvoiceExpectation::create();
        $this->invoiceBatchRepository = InvoiceBatchRepositoryExpectation::create();

        $this->subject = new InvoiceGeneratorImpl($this->billableItemsViewRepository->mock, $this->invoiceRepository->mock, $this->invoiceBatchRepository->mock);
    }

    public function test_it_skips_creation_when_no_billable_items_exist(): void
    {
        $memberId = MemberId::create(1);
        $invoiceDate = new DateTimeImmutable('2026-05-25');

        $this->billableItemsViewRepository->expectsListBillableItemsForMember(
            $invoiceDate,
            $memberId,
            new BillableItemList([]),
        );

        $this->subject->generate(new InvoiceTarget($memberId, $invoiceDate));
    }

    public function test_it_creates_invoice_when_billable_items_exist(): void
    {
        $memberId = MemberId::create(1);
        $invoiceDate = new DateTimeImmutable('2026-05-25');
        $items = new BillableItemList([
            new BillableItem(BillableItemId::create(10), new CompoundPrice(10.0, 2.1), 1.0, 'A'),
        ]);

        $expectedInvoice = new ApplyInvoiceLines($memberId, $invoiceDate, $items);
        $return = new AppliedInvoiceWithLineIds(false, InvoiceId::create(9), [InvoiceLineId::create(10)]);

        $this->billableItemsViewRepository->expectsListBillableItemsForMember($invoiceDate, $memberId, $items);
        $this->invoiceRepository->expectsApplyLines($expectedInvoice, $return);

        $this->subject->generate(new InvoiceTarget($memberId, $invoiceDate));
    }

    public function test_it_attaches_to_batch(): void
    {
        $batchId = InvoiceBatchId::create(10);
        $memberId = MemberId::create(1);
        $invoiceId = InvoiceId::create(9);
        $invoiceDate = new DateTimeImmutable('2026-05-25');
        $items = new BillableItemList([
            new BillableItem(BillableItemId::create(10), new CompoundPrice(10.0, 2.1), 1.0, 'A'),
        ]);

        $expectedInvoice = new ApplyInvoiceLines($memberId, $invoiceDate, $items);
        $return = new AppliedInvoiceWithLineIds(false, $invoiceId, [InvoiceLineId::create(10)]);

        $this->billableItemsViewRepository->expectsListBillableItemsForMember($invoiceDate, $memberId, $items);
        $this->invoiceRepository->expectsApplyLines($expectedInvoice, $return);
        $this->invoiceBatchRepository->expectsAttachInvoice($invoiceId, $batchId);

        $this->subject->generate(new InvoiceTarget($memberId, $invoiceDate, $batchId));
    }
}
