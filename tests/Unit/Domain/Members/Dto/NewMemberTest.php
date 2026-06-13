<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Dto;

use App\Domain\Members\Dto\NewMember;
use App\Domain\Members\Dto\NewMemberMembershipInformation;
use App\Domain\Members\Dto\NewMemberPaymentInformation;
use App\Domain\Members\Dto\NewMemberPersonalInformation;
use App\Domain\Members\Gender;
use App\Domain\Members\MembershipId;
use DateTimeImmutable;
use Tests\UnitTestCase;

final class NewMemberTest extends UnitTestCase
{
    public function test_constructor_stores_all_sub_dtos(): void
    {
        $membership = new NewMemberMembershipInformation(MembershipId::create(1));
        $personal = new NewMemberPersonalInformation(
            firstName: 'Jan',
            infixName: '',
            lastName: 'Vries',
            gender: Gender::Male,
            birthdate: new DateTimeImmutable('1990-01-15'),
            email: 'jan@example.com',
            addressStreet: 'Surfstrand',
            addressHousenumber: '2',
            addressHousenumberAddition: '',
            addressPostalcode: '1324CT',
            addressCity: 'Almere',
        );
        $payment = new NewMemberPaymentInformation(
            iban: 'NL91ABNA0417164300',
            bic: 'ABNANL2A',
            accountHolderName: 'J. de Vries',
            mandateAcceptedDate: new DateTimeImmutable('2024-02-01'),
        );

        $dto = new NewMember(
            membershipInformation: $membership,
            personalInformation: $personal,
            paymentInformation: $payment,
        );

        static::assertSame($membership, $dto->membershipInformation);
        static::assertSame($personal, $dto->personalInformation);
        static::assertSame($payment, $dto->paymentInformation);
        static::assertSame([], $dto->registrationData);
    }

    public function test_registration_answers_defaults_to_empty_array(): void
    {
        $dto = new NewMember(
            membershipInformation: new NewMemberMembershipInformation(MembershipId::create(1)),
            personalInformation: new NewMemberPersonalInformation(
                firstName: 'Jan',
                infixName: '',
                lastName: 'Vries',
                gender: Gender::Male,
                birthdate: new DateTimeImmutable('1990-01-15'),
                email: 'jan@example.com',
                addressStreet: 'Surfstrand',
                addressHousenumber: '2',
                addressHousenumberAddition: '',
                addressPostalcode: '1324CT',
                addressCity: 'Almere',
            ),
            paymentInformation: new NewMemberPaymentInformation(
                iban: 'NL91ABNA0417164300',
                bic: 'ABNANL2A',
                accountHolderName: 'J. de Vries',
                mandateAcceptedDate: new DateTimeImmutable('2024-02-01'),
            ),
        );

        static::assertSame([], $dto->registrationData);
    }

    public function test_registration_answers_can_be_provided(): void
    {
        $answers = ['windsurfing_lessons' => true, 'rtc_lessons' => false];

        $dto = new NewMember(
            membershipInformation: new NewMemberMembershipInformation(MembershipId::create(1)),
            personalInformation: new NewMemberPersonalInformation(
                firstName: 'Jan',
                infixName: '',
                lastName: 'Vries',
                gender: Gender::Male,
                birthdate: new DateTimeImmutable('1990-01-15'),
                email: 'jan@example.com',
                addressStreet: 'Surfstrand',
                addressHousenumber: '2',
                addressHousenumberAddition: '',
                addressPostalcode: '1324CT',
                addressCity: 'Almere',
            ),
            paymentInformation: new NewMemberPaymentInformation(
                iban: 'NL91ABNA0417164300',
                bic: 'ABNANL2A',
                accountHolderName: 'J. de Vries',
                mandateAcceptedDate: new DateTimeImmutable('2024-02-01'),
            ),
            registrationData: $answers,
        );

        static::assertSame($answers, $dto->registrationData);
    }
}
