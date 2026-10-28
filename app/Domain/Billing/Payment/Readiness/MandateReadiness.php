<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Members\PaymentMethod;
use DateTimeInterface;

final readonly class MandateReadiness
{
    public function __construct(
        public MandateReadinessStatus $status,
        public ?string $iban = null,
        public ?string $bic = null,
        public ?MandateId $mandateId = null,
        public ?DateTimeInterface $signedOn = null,
        public SequenceType $expectedSequenceType = SequenceType::Recurring,
        public PaymentMethod $paymentMethod = PaymentMethod::DirectDebit,
    ) {}

    public static function missing(PaymentMethod $paymentMethod = PaymentMethod::DirectDebit): self
    {
        return new self(MandateReadinessStatus::Missing, paymentMethod: $paymentMethod);
    }

    public function isUsable(): bool
    {
        return $this->status === MandateReadinessStatus::Active;
    }
}
