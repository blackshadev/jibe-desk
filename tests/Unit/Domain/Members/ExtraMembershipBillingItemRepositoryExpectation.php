<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Members\ExtraMembershipBillingItemRepository;
use App\Domain\Members\ExtraMembershipItemCode;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class ExtraMembershipBillingItemRepositoryExpectation
{
    private function __construct(public MockInterface&ExtraMembershipBillingItemRepository $mock)
    {
    }

    public static function create(): self
    {
        return new self(Mockery::mock(ExtraMembershipBillingItemRepository::class));
    }

    public function expectsGetByCode(ExtraMembershipItemCode $code, BillableItemId $return): void
    {
        $this->mock
            ->expects('getByCode')
            ->with(equalTo($code))
            ->andReturn($return);
    }
}
