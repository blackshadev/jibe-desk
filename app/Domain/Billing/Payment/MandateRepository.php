<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use App\Domain\Members\MemberId;
use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface MandateRepository
{
    public function create(MemberId $memberId, MandateDetails $details): PaymentInformationId;

    public function update(PaymentInformationId $id, MandateCorrection $details): void;

    public function revoke(PaymentInformationId $id, DateTimeInterface $revokedAt): void;

    /**
     * Stamp last_collected_at on the active mandates of every member with a
     * positive, non-credit invoice in the batch.
     */
    public function stampCollectionsForBatch(SepaBatchId $batchId, DateTimeInterface $collectedAt): int;
}
