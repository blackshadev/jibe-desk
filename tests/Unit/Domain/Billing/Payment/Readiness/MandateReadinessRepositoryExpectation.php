<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Payment\Readiness\MandateReadiness;
use App\Domain\Billing\Payment\Readiness\MandateReadinessRepository;
use App\Domain\Members\MemberId;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class MandateReadinessRepositoryExpectation
{
    private function __construct(
        public MockInterface&MandateReadinessRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(MandateReadinessRepository::class));
    }

    /** @param list<MemberId> $memberIds */
    public function expectsForMembers(array $memberIds, MandateReadiness|array $return): void
    {
        $this->mock
            ->expects('forMembers')
            ->with(equalTo($memberIds))
            ->andReturn($return);
    }
}
