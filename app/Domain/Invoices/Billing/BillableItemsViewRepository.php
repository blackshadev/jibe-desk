<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing;

use App\Domain\Members\MemberId;
use App\Domain\Members\MemberIdList;
use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BillableItemsViewRepository
{
    public function listBillableMembers(DateTimeInterface $when): MemberIdList;

    public function listBillableItemsForMember(DateTimeInterface $when, MemberId $memberId): BillableItemList;
}
