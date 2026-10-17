<?php

declare(strict_types=1);

namespace App\Domain\Billing\Subscriptions;

use App\Domain\Members\MemberId;
use App\Domain\Members\MemberIdList;
use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;
use App\Domain\Billing\BillableItems\BillableItemList;

#[Autowire]
interface BillableItemsViewRepository
{
    public function listBillableMembers(DateTimeInterface $when): MemberIdList;

    public function listBillableItemsForMember(DateTimeInterface $when, MemberId $memberId): BillableItemList;
}
