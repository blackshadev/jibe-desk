<?php

declare(strict_types=1);

namespace App\Domain\Billing\Subscriptions;

use App\Domain\Members\MemberId;
use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;
use App\Domain\Billing\BillableItems\BillableItemId;
use App\Domain\Billing\BillableItems\BillableItemIdList;

#[Autowire]
interface BillableItemInstanceRepository
{
    public function removeMany(MemberId $memberId, BillableItemIdList $billableItemIds): void;

    public function add(MemberId $memberId, BillableItemId $billableItemId, ?DateTimeInterface $endDate = null, ?DateTimeInterface $startDate = null): BillableItemInstanceId;

    public function ensure(MemberId $memberId, BillableItemId $billableItemId): void;

    public function stop(BillableItemInstanceId $instanceId, DateTimeInterface $endDate): void;

    public function stopAll(MemberId $memberId, DateTimeInterface $endDate): void;

    public function updateEndDate(BillableItemInstanceId $instanceId, ?DateTimeInterface $endDate): void;
}
