<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Payment\MandateRepository;
use App\Domain\Billing\Payment\PaymentInformationIdList;
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

    public function expectsStampCollections(PaymentInformationIdList $ids, DateTimeInterface $collectedAt): void
    {
        $this->mock
            ->expects('stampCollections')
            ->with(equalTo($ids), equalTo($collectedAt))
            ->andReturn(count($ids->ids));
    }
}
