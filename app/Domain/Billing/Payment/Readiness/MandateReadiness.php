<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\SequenceType;
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
    ) {}

    public static function missing(): self
    {
        return new self(MandateReadinessStatus::Missing);
    }

    public function isUsable(): bool
    {
        return $this->status === MandateReadinessStatus::Active;
    }
}
