<?php

declare(strict_types=1);

namespace App\Domain\Members\Dto;

use App\Domain\Members\Gender;
use DateTimeInterface;

final readonly class NewMemberPersonalInformation
{
    public function __construct(
        public string $firstName,
        public string $infixName,
        public string $lastName,
        public Gender $gender,
        public DateTimeInterface $birthdate,
        public string $email,
        public string $addressStreet,
        public string $addressHousenumber,
        public string $addressHousenumberAddition,
        public string $addressPostalcode,
        public string $addressCity,
    ) {
    }
}
