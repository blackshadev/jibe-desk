<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Payment\MandateRepository;
use App\Domain\Billing\Payment\SepaBatchId;
use DateTimeInterface;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class MandateRepositoryExpectation
{
    private function __construct(
        public MockInterface&MandateRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(MandateRepository::class));
    }

    public function expectsStampCollectionsForBatch(SepaBatchId $batchId, DateTimeInterface $collectedAt): void
    {
        $this->mock
            ->expects('stampCollectionsForBatch')
            ->with(equalTo($batchId), equalTo($collectedAt))
            ->andReturn(0);
    }
}
