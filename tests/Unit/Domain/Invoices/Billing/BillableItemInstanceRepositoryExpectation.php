<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillableItemInstanceRepository;
use App\Domain\Members\MemberId;
use DateTimeInterface;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;
use function PHPUnit\Framework\isNull;

final readonly class BillableItemInstanceRepositoryExpectation
{
    private function __construct(
        public MockInterface&BillableItemInstanceRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(BillableItemInstanceRepository::class));
    }

    public function expectsAdd(
        MemberId $memberId,
        int $billableItemId,
        ?DateTimeInterface $endDate,
        ?DateTimeInterface $startDate,
        BillableItemInstanceId $return,
    ): void {
        $this->mock
            ->expects('add')
            ->with(
                equalTo($memberId),
                equalTo(BillableItemId::create($billableItemId)),
                $endDate ? equalTo($endDate) : isNull(),
                $startDate ? equalTo($startDate) : isNull(),
            )
            ->andReturn($return);
    }

    public function expectsStop(BillableItemInstanceId $instanceId): void
    {
        $this->mock
            ->expects('stop')
            ->with(equalTo($instanceId));
    }

    public function expectsUpdateEndDate(BillableItemInstanceId $instanceId, ?DateTimeInterface $endDate): void
    {
        $this->mock
            ->expects('updateEndDate')
            ->with(equalTo($instanceId), equalTo($endDate));
    }
}
