<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\Billing\BillableItemList;
use App\Domain\Invoices\Billing\BillableItemsViewRepository;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberIdList;
use DateTimeInterface;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class BillableItemsViewRepositoryExpectation
{
    private function __construct(
        public MockInterface&BillableItemsViewRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(BillableItemsViewRepository::class));
    }

    public function expectsListBillableItemsForMember(DateTimeInterface $when, MemberId $memberId, BillableItemList $return): void
    {
        $this->mock
            ->expects('listBillableItemsForMember')
            ->with(equalTo($when), equalTo($memberId))
            ->andReturn($return);
    }

    public function expectsListBillableMembers(DateTimeInterface $when, MemberIdList $return): void
    {
        $this->mock
            ->expects('listBillableMembers')
            ->with(equalTo($when))
            ->andReturn($return);
    }
}
