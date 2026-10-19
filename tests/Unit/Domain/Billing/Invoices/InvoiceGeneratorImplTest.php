<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Invoices;

use App\Domain\Billing\BillableItems\BillableItem;
use App\Domain\Billing\BillableItems\BillableItemId;
use App\Domain\Billing\BillableItems\BillableItemList;
use App\Domain\Billing\Invoices\AppliedInvoiceWithLineIds;
use App\Domain\Billing\Invoices\ApplyInvoiceLines;
use App\Domain\Billing\Invoices\InvoiceGeneratorImpl;
use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceLineId;
use App\Domain\Billing\Invoices\InvoiceTarget;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Bookkeeping\CostCenters\CostCenterId;
use App\Domain\Members\MemberId;
use App\Domain\SharedKernel\CompoundPrice;
use DateTimeImmutable;
use Override;
use Tests\Unit\Domain\Billing\Payment\SepaBatchRepositoryExpectation;
use Tests\UnitTestCase;

final class InvoiceGeneratorImplTest extends UnitTestCase
{
    private BillableItemsViewRepositoryExpectation $billableItemsViewRepository;
    private CreateInvoiceExpectation $invoiceRepository;
    private SepaBatchRepositoryExpectation $sepaBatchRepository;
    private InvoiceGeneratorImpl $subject;

    #[Override]
    protected function setup(): void
    {
        parent::setup();

        $this->billableItemsViewRepository = BillableItemsViewRepositoryExpectation::create();
        $this->invoiceRepository = CreateInvoiceExpectation::create();
        $this->sepaBatchRepository = SepaBatchRepositoryExpectation::create();

        $this->subject = new InvoiceGeneratorImpl($this->billableItemsViewRepository->mock, $this->invoiceRepository->mock, $this->sepaBatchRepository->mock);
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
            new BillableItem(BillableItemId::create(10), new CompoundPrice(10.0, 2.1), 1.0, 'A', CostCenterId::create(1)),
        ]);

        $expectedInvoice = new ApplyInvoiceLines($memberId, $invoiceDate, $items);
        $return = new AppliedInvoiceWithLineIds(false, InvoiceId::create(9), [InvoiceLineId::create(10)]);

        $this->billableItemsViewRepository->expectsListBillableItemsForMember($invoiceDate, $memberId, $items);
        $this->invoiceRepository->expectsApplyLines($expectedInvoice, $return);

        $this->subject->generate(new InvoiceTarget($memberId, $invoiceDate));
    }

    public function test_it_attaches_to_batch(): void
    {
        $batchId = SepaBatchId::create(10);
        $memberId = MemberId::create(1);
        $invoiceId = InvoiceId::create(9);
        $invoiceDate = new DateTimeImmutable('2026-05-25');
        $items = new BillableItemList([
            new BillableItem(BillableItemId::create(10), new CompoundPrice(10.0, 2.1), 1.0, 'A', CostCenterId::create(1)),
        ]);

        $expectedInvoice = new ApplyInvoiceLines($memberId, $invoiceDate, $items);
        $return = new AppliedInvoiceWithLineIds(false, $invoiceId, [InvoiceLineId::create(10)]);

        $this->billableItemsViewRepository->expectsListBillableItemsForMember($invoiceDate, $memberId, $items);
        $this->invoiceRepository->expectsApplyLines($expectedInvoice, $return);
        $this->sepaBatchRepository->expectsAttachInvoice($invoiceId, $batchId);

        $this->subject->generate(new InvoiceTarget($memberId, $invoiceDate, $batchId));
    }
}
