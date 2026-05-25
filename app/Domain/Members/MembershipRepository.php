<?php

declare(strict_types=1);

namespace App\Domain\Members;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface MembershipRepository
{
    public function getById(MembershipId $membershipId): Membership;

    public function all(): MembershipList;
}
