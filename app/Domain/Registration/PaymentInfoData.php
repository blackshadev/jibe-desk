<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * @phpstan-type PaymentInfoDataArray array{
 *     bankingAccountNumber: string,
 *     bankingBic: string,
 *     bankingAccountHolderName: string,
 *     mandateAcceptedDate: non-falsy-string|null,
 * }
 */

final class PaymentInfoData
{
    public function __construct(
        public string $bankingAccountNumber,
        public string $bankingBic,
        public string $bankingAccountHolderName,
        public ?DateTimeInterface $mandateAcceptedDate,
    ) {}

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
        $mandateAcceptedDate = null;
        if ($data['mandateAcceptedDate'] !== null) {
            $mandateAcceptedDate = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $data['mandateAcceptedDate']);
            if ($mandateAcceptedDate === false) {
                throw new InvalidArgumentException('Invalid mandateAcceptedDate format');
            }
        }

        return new self(
            bankingAccountNumber: $data['bankingAccountNumber'],
            bankingBic: $data['bankingBic'],
            bankingAccountHolderName: $data['bankingAccountHolderName'],
            mandateAcceptedDate: $mandateAcceptedDate,
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
