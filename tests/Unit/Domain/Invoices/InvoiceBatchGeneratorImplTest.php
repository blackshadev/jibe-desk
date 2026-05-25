<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\GenerateInvoice;
use App\Domain\Invoices\InvoiceBatch;
use App\Domain\Invoices\InvoiceBatchGeneratorImpl;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberIdList;
use DateTimeImmutable;
use Tests\UnitTestCase;

final class InvoiceBatchGeneratorImplTest extends UnitTestCase
{
    public function test_it_generates_one_invoice_per_billable_member(): void
    {
        $invoiceDate = new DateTimeImmutable('2026-05-25');
        $batch = new InvoiceBatch(InvoiceBatchId::create(9), $invoiceDate);
        $memberIds = new MemberIdList([MemberId::create(1), MemberId::create(2)]);

        $billableItemsViewRepository = BillableItemsViewRepositoryExpectation::create();
        $invoiceGenerator = InvoiceGeneratorExpectation::create();

        $billableItemsViewRepository->expectsListBillableMembers($invoiceDate, $memberIds);
        $invoiceGenerator->expectsGenerate(new GenerateInvoice(MemberId::create(1), $invoiceDate, $batch->id));
        $invoiceGenerator->expectsGenerate(new GenerateInvoice(MemberId::create(2), $invoiceDate, $batch->id));

        $subject = new InvoiceBatchGeneratorImpl($invoiceGenerator->mock, $billableItemsViewRepository->mock);

        $subject->generate($batch);
    }

    public function test_it_does_not_generate_invoices_when_no_billable_members_exist(): void
    {
        $invoiceDate = new DateTimeImmutable('2026-05-25');
        $batch = new InvoiceBatch(InvoiceBatchId::create(9), $invoiceDate);

        $billableItemsViewRepository = BillableItemsViewRepositoryExpectation::create();
        $invoiceGenerator = InvoiceGeneratorExpectation::create();

        $billableItemsViewRepository->expectsListBillableMembers($invoiceDate, new MemberIdList([]));

        $subject = new InvoiceBatchGeneratorImpl($invoiceGenerator->mock, $billableItemsViewRepository->mock);

        $subject->generate($batch);
    }
}
