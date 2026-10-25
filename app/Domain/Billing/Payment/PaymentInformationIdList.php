<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use Webmozart\Assert\Assert;

final class PaymentInformationIdList
{
    /** @param PaymentInformationId[] $ids */
    public function __construct(
        public array $ids,
    ) {
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        Assert::allIsInstanceOf($ids, PaymentInformationId::class);
    }

    /** @param int[] $array */
    public static function fromArray(array $array): self
    {
        return new self(array_map(PaymentInformationId::create(...), $array));
    }

    /** @return int[] */
    public function values(): array
    {
        return array_map(static fn (PaymentInformationId $id): int => $id->value, $this->ids);
    }
}
