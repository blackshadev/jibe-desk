<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Gender;
use DateTimeInterface;

final readonly class MappedMemberRow
{
    public function __construct(
        public string $lidnr,
        public string $firstName,
        public string $infixName,
        public string $lastName,
        public string $fullName,
        public string $email,
        public ?string $contactEmail,
        public DateTimeInterface $birthdate,
        public Gender $gender,
        public string $addressStreet,
        public string $addressHousenumber,
        public ?string $addressHousenumberAddition,
        public string $addressPostalcode,
        public string $addressCity,
        public ?string $membershipType,
        public string $membershipName,
        public bool $isVolunteer,
        public DateTimeInterface $startDate,
        public ?DateTimeInterface $stopDate,
        public string $iban,
        public string $bic,
        public string $accountHolderName,
        public bool $attendsRtc,
        public bool $hasContainer,
    ) {}

    public function isLeaver(): bool
    {
        return $this->stopDate !== null;
    }

    public function isInstructor(): bool
    {
        return $this->membershipType === 'zeil-instructeur - 2026';
    }

    public function hasMembershipType(): bool
    {
        return $this->membershipType !== null;
    }

    public function addressKey(): string
    {
        return sprintf(
            '%s|%s|%s|%s',
            strtolower($this->addressStreet),
            strtolower($this->addressHousenumber),
            strtolower($this->addressHousenumberAddition ?? ''),
            $this->addressPostalcode,
        );
    }
}
