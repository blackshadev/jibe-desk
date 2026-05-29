<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing;

use App\Domain\Members\MemberId;
use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BillableItemInstanceRepository
{
    public function removeMany(MemberId $memberId, BillableItemIdList $billableItemIds): void;

    public function add(MemberId $memberId, BillableItemId $billableItemId, ?DateTimeInterface $endDate = null): BillableItemInstanceId;

    public function ensure(MemberId $memberId, BillableItemId $contributionId): void;

    public function stop(BillableItemInstanceId $instanceId): void;
}
