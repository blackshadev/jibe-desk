<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Jobs;

use App\Domain\Invoices\Jobs\FinishSepaBatchGeneration;
use App\Domain\Invoices\SepaBatchId;
use Carbon\CarbonImmutable;
use Override;
use Tests\Unit\Domain\Invoices\SepaBatchServiceExpectation;
use Tests\UnitTestCase;

final class FinishSepaBatchGenerationTest extends UnitTestCase
{
    private SepaBatchServiceExpectation $batchService;

    #[Override]
    protected function setup(): void
    {
        parent::setup();

        $this->batchService = SepaBatchServiceExpectation::create();
    }

    public function test_handle_marks_generation_finished(): void
    {
        $batchId = SepaBatchId::create(10);

        $this->batchService->expectsFinishGeneration($batchId);

        $job = new FinishSepaBatchGeneration($batchId);

        $job->handle($this->batchService->mock);
    }

    public function test_handle_does_nothing_when_batch_is_cancelled(): void
    {
        $batchId = SepaBatchId::create(10);

        $job = new FinishSepaBatchGeneration($batchId);
        $job->withFakeBatch(cancelledAt: CarbonImmutable::now());

        $job->handle($this->batchService->mock);

        $this->batchService->mock->shouldNotHaveReceived('finishGeneration');
    }
}
