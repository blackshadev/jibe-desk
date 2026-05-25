<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemIdList;
use App\Domain\Invoices\Billing\BillableItemInstanceRepository;
use App\Domain\Members\MemberId;
use Mockery;
use Mockery\MockInterface;
use function PHPUnit\Framework\equalTo;

final readonly class BillableItemRepositoryExpectation
{
    private function __construct(public MockInterface&BillableItemInstanceRepository $mock)
    {
    }

    public static function create(): self
    {
        return new self(Mockery::mock(BillableItemInstanceRepository::class));
    }

    public function expectsRemoveInstances(MemberId $memberId, BillableItemIdList $billableItemIds): void
    {
        $this->mock
            ->expects('removeMany')
            ->with(equalTo($memberId), equalTo($billableItemIds));
    }

    public function expectsAddInstance(MemberId $memberId, BillableItemId $billableItemId): void
    {
        $this->mock
            ->expects('add')
            ->with(equalTo($memberId), equalTo($billableItemId));
    }
}
