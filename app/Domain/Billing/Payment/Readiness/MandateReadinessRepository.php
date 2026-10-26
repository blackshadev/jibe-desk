<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use App\Domain\Members\MemberId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface MandateReadinessRepository
{
    /**
     * @param list<MemberId> $memberIds
     * @return array<int, MandateReadiness> keyed by member id
     */
    public function forMembers(array $memberIds): array;
}
