<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Jobs;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceTarget;
use App\Domain\Invoices\Jobs\GenerateInvoice;
use App\Domain\Members\MemberId;
use Carbon\CarbonImmutable;
use Tests\Unit\Domain\Invoices\InvoiceGeneratorExpectation;
use Tests\UnitTestCase;

final class GenerateInvoiceTest extends UnitTestCase
{
    private InvoiceGeneratorExpectation $generator;

    protected function setup(): void
    {
        parent::setup();

        $this->generator = InvoiceGeneratorExpectation::create();
    }

    public function test_handle_generates_invoice_for_given_target(): void
    {
        $target = new InvoiceTarget(
            memberId: MemberId::create(1),
            invoiceDate: CarbonImmutable::parse('2026-05-25'),
            batchId: InvoiceBatchId::create(10),
        );
        $this->generator->expectsGenerate($target);

        $job = new GenerateInvoice($target);

        $job->handle($this->generator->mock);
    }

    public function test_handle_does_nothing_when_batch_is_cancelled(): void
    {
        $target = new InvoiceTarget(
            memberId: MemberId::create(1),
            invoiceDate: CarbonImmutable::parse('2026-05-25'),
        );
        $job = new GenerateInvoice($target);
        $job->withFakeBatch(cancelledAt: CarbonImmutable::now());

        $job->handle($this->generator->mock);

        $this->generator->mock->shouldNotHaveReceived('generate');
    }
}
