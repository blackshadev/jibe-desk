<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\AppliedInvoiceWithLineIds;
use App\Domain\Invoices\ApplyInvoiceLines;
use App\Domain\Invoices\Billing\BillableItem;
use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemList;
use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\GenerateInvoice;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceGeneratorImpl;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceLineId;
use App\Domain\Members\MemberId;
use DateTimeImmutable;
use Tests\UnitTestCase;

final class InvoiceGeneratorImplTest extends UnitTestCase
{
    public function test_it_skips_creation_when_no_billable_items_exist(): void
    {
        $memberId = MemberId::create(1);
        $invoiceDate = new DateTimeImmutable('2026-05-25');

        $billableItemsViewRepository = BillableItemsViewRepositoryExpectation::create();
        $invoiceRepository = CreateInvoiceExpectation::create();

        $billableItemsViewRepository->expectsListBillableItemsForMember(
            $invoiceDate,
            $memberId,
            new BillableItemList([])
        );

        $subject = new InvoiceGeneratorImpl($billableItemsViewRepository->mock, $invoiceRepository->mock);

        $subject->generate(new GenerateInvoice($memberId, $invoiceDate));
    }

    public function test_it_creates_invoice_when_billable_items_exist(): void
    {
        $memberId = MemberId::create(1);
        $invoiceDate = new DateTimeImmutable('2026-05-25');
        $items = new BillableItemList([
            new BillableItem(BillableItemId::create(10), new CompoundPrice(10.0, 2.1), 1.0, 'A'),
        ]);

        $billableItemsViewRepository = BillableItemsViewRepositoryExpectation::create();
        $invoiceRepository = CreateInvoiceExpectation::create();
        $expectedInvoice = new ApplyInvoiceLines($memberId, $invoiceDate, $items);
        $return = new AppliedInvoiceWithLineIds(false, InvoiceId::create(9), [InvoiceLineId::create(10)]);

        $billableItemsViewRepository->expectsListBillableItemsForMember($invoiceDate, $memberId, $items);
        $invoiceRepository->expectsApplyLines($expectedInvoice, $return);

        $subject = new InvoiceGeneratorImpl($billableItemsViewRepository->mock, $invoiceRepository->mock);

        $subject->generate(new GenerateInvoice($memberId, $invoiceDate));
    }
}
