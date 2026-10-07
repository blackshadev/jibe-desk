<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Jobs;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\Jobs\FinishInvoiceBatchGeneration;
use Carbon\CarbonImmutable;
use Override;
use Tests\Unit\Domain\Invoices\InvoiceBatchServiceExpectation;
use Tests\UnitTestCase;

final class FinishInvoiceBatchGenerationTest extends UnitTestCase
{
    private InvoiceBatchServiceExpectation $batchService;

    #[Override]
    protected function setup(): void
    {
        parent::setup();

        $this->batchService = InvoiceBatchServiceExpectation::create();
    }

    public function test_handle_marks_generation_finished(): void
    {
        $batchId = InvoiceBatchId::create(10);

        $this->batchService->expectsFinishGeneration($batchId);

        $job = new FinishInvoiceBatchGeneration($batchId);

        $job->handle($this->batchService->mock);
    }

    public function test_handle_does_nothing_when_batch_is_cancelled(): void
    {
        $batchId = InvoiceBatchId::create(10);

        $job = new FinishInvoiceBatchGeneration($batchId);
        $job->withFakeBatch(cancelledAt: CarbonImmutable::now());

        $job->handle($this->batchService->mock);

        $this->batchService->mock->shouldNotHaveReceived('finishGeneration');
    }
}
