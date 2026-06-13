<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Activities;

use App\Domain\Activities\Activity;
use App\Domain\Activities\ActivityId;
use App\Domain\Activities\ActivityRepository;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Members\MemberId;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class ActivityRepositoryExpectation
{
    private function __construct(
        public MockInterface&ActivityRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(ActivityRepository::class));
    }

    public function expectsGetById(ActivityId $activityId, Activity $activity): void
    {
        $this->mock
            ->expects('getById')
            ->with(equalTo($activityId))
            ->andReturn($activity);
    }

    public function expectsAttach(ActivityId $activityId, MemberId $memberId, BillableItemInstanceId $instanceId): void
    {
        $this->mock
            ->expects('attach')
            ->with(equalTo($activityId), equalTo($memberId), equalTo($instanceId));
    }
}
