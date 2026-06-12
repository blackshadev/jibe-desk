<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use App\Domain\Members\Gender;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * @phpstan-type PersonalInfoDataArray array{
 *     firstName: string,
 *     infixName?: string,
 *     lastName: string,
 *     email: string,
 *     gender: string,
 *     birthdate: string,
 *     addressStreet: string,
 *     addressHousenumber: string,
 *     addressHousenumberAddition: string,
 *     addressPostalcode: string,
 *     addressCity: string,
 * }
 */

final class PersonalInfoData
{
    public function __construct(
        public string $firstName,
        public string $infixName,
        public string $lastName,
        public string $email,
        public Gender $gender,
        public DateTimeInterface $birthdate,
        public string $addressStreet,
        public string $addressHousenumber,
        public string $addressHousenumberAddition,
        public string $addressPostalcode,
        public string $addressCity,
    ) {
    }

    public static function createDefault(): self
    {
        return new self(
            firstName: '',
            infixName: '',
            lastName: '',
            email: '',
            gender: Gender::Unknown,
            birthdate: new DateTimeImmutable('2000-01-01'),
            addressStreet: '',
            addressHousenumber: '',
            addressHousenumberAddition: '',
            addressPostalcode: '',
            addressCity: '',
        );
    }

    /** @param PersonalInfoDataArray $data */
    public static function createFromArray(array $data): self
    {
        return new self(
            firstName: $data['firstName'],
            infixName: $data['infixName'] ?? '',
            lastName: $data['lastName'],
            email: $data['email'],
            gender: Gender::from($data['gender']),
            birthdate: DateTimeImmutable::createFromFormat('Y-m-d', $data['birthdate']) ?: throw new InvalidArgumentException('Invalid birthdate'),
            addressStreet: $data['addressStreet'],
            addressHousenumber: $data['addressHousenumber'],
            addressHousenumberAddition: $data['addressHousenumberAddition'],
            addressPostalcode: $data['addressPostalcode'],
            addressCity: $data['addressCity'],
        );
    }

    /** @return PersonalInfoDataArray */
    public function toArray(): array
    {
        return [
            'firstName' => $this->firstName,
            'infixName' => $this->infixName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'gender' => $this->gender->value,
            'birthdate' => $this->birthdate->format('Y-m-d'),
            'addressStreet' => $this->addressStreet,
            'addressHousenumber' => $this->addressHousenumber,
            'addressHousenumberAddition' => $this->addressHousenumberAddition,
            'addressPostalcode' => $this->addressPostalcode,
            'addressCity' => $this->addressCity,
        ];
    }
}
