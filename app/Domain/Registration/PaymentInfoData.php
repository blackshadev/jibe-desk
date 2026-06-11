<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * @phpstan-type PaymentInfoDataArray array{
 *     bankingAccountNumber?: string,
 *     bankingBic?: string,
 *     bankingAccountHolderName?: string,
 *     mandateAcceptedDate?: string,
 * }
 */

final class PaymentInfoData
{
    public function __construct(
        public string $bankingAccountNumber,
        public string $bankingBic,
        public string $bankingAccountHolderName,
        public ?DateTimeInterface $mandateAcceptedDate,
    ) {
    }

    public static function createDefault(): self
    {
        return new self(
            bankingAccountNumber: '',
            bankingBic: '',
            bankingAccountHolderName: '',
            mandateAcceptedDate: null,
        );
    }

    /** @param PaymentInfoDataArray $data */
    public static function createFromArray(array $data): self
    {
        return new self(
            bankingAccountNumber: $data['bankingAccountNumber'] ?? '',
            bankingBic: $data['bankingBic'] ?? '',
            bankingAccountHolderName: $data['bankingAccountHolderName'] ?? '',
            mandateAcceptedDate: !empty($data['mandateAcceptedDate']) ? DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $data['mandateAcceptedDate']) : null,
        );
    }

    /** @return PaymentInfoDataArray */
    public function toArray(): array
    {
        return [
            'bankingAccountNumber' => $this->bankingAccountNumber,
            'bankingBic' => $this->bankingBic,
            'bankingAccountHolderName' => $this->bankingAccountHolderName,
            'mandateAcceptedDate' => $this->mandateAcceptedDate?->format('c'),
        ];
    }
}
