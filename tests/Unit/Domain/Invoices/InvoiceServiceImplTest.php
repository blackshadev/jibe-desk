<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\Invoices\InvoiceServiceImpl;
use Override;
use Tests\Unit\Domain\Bookkeeping\BookkeepingRecordRepositoryExpectation;
use Tests\UnitTestCase;

final class InvoiceServiceImplTest extends UnitTestCase
{
    private CreateInvoiceExpectation $repo;
    private BookkeepingRecordRepositoryExpectation $bookkeepingRepo;
    private InvoiceServiceImpl $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = CreateInvoiceExpectation::create();
        $this->bookkeepingRepo = BookkeepingRecordRepositoryExpectation::create();

        $this->service = new InvoiceServiceImpl(
            $this->repo->mock,
            $this->bookkeepingRepo->mock,
        );
    }

    public function test_mark_as_paid_updates_status_and_creates_bookkeeping_records(): void
    {
        $id = InvoiceId::create(1);
        $ids = new InvoiceIdList([$id]);

        $this->repo->expectsMarkAsPaid($ids);
        $this->bookkeepingRepo->expectsCreateForInvoice($ids);

        $this->service->markAsPaid($ids);
    }
}
