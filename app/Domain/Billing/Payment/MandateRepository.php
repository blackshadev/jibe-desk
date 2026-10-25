<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use App\Domain\Members\MemberId;
use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface MandateRepository
{
    public function register(MemberId $memberId, MandateDetails $details): PaymentInformationId;

    public function correctDetails(PaymentInformationId $id, MandateCorrection $details): void;

    public function revoke(PaymentInformationId $id, DateTimeInterface $revokedAt): void;

    public function stampCollections(PaymentInformationIdList $ids, DateTimeInterface $collectedAt): int;
}
