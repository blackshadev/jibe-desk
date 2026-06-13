<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration;

use App\Domain\Registration\FormData;
use App\Domain\Registration\MembershipData;
use App\Domain\Registration\PaymentInfoData;
use App\Domain\Registration\PersonalInfoData;
use App\Domain\Registration\Step;
use Tests\UnitTestCase;

final class FormDataTest extends UnitTestCase
{
    private const PERSONAL_INFO_DATA = [
        'firstName' => 'Jan',
        'infixName' => '',
        'lastName' => 'Vries',
        'email' => 'jan@example.com',
        'gender' => 'M',
        'birthdate' => '1990-01-15',
        'addressStreet' => 'Surfstrand',
        'addressHousenumber' => '2',
        'addressHousenumberAddition' => 'A',
        'addressPostalcode' => '1324CT',
        'addressCity' => 'Almere',
    ];

    private const PAYMENT_INFO_DATA = [
        'bankingAccountNumber' => 'NL91ABNA0417164300',
        'bankingBic' => 'ABNANL2A',
        'bankingAccountHolderName' => 'J. de Vries',
        'mandateAcceptedDate' => '2024-02-01T03:04:05+00:00',
    ];

    private const MEMBERSHIP_DATA = [
        'regularWindsurfingLessons' => true,
        'rtc' => false,
        'clubhouseAccess' => true,
        'boardStorage' => false,
        'watersportFederationNumber' => '12345',
    ];

    public function test_create_default_has_initial_step_and_empty_data(): void
    {
        $formData = FormData::createDefault();

        static::assertSame(Step::Initial, $formData->step);
        static::assertSame('', $formData->membership->watersportFederationNumber);
        static::assertSame('', $formData->personalInfo->firstName);
        static::assertSame('', $formData->paymentInfo->bankingAccountNumber);
    }

    public function test_create_from_array_hydrates_all_data(): void
    {
        $formData = FormData::create([
            'step' => Step::Membership->value,
            'membership' => self::MEMBERSHIP_DATA,
            'personalInfo' => self::PERSONAL_INFO_DATA,
            'paymentInfo' => self::PAYMENT_INFO_DATA,
        ]);

        static::assertSame(Step::Membership, $formData->step);
        static::assertTrue($formData->membership->regularWindsurfingLessons);
        static::assertFalse($formData->membership->rtc);
        static::assertTrue($formData->membership->clubhouseAccess);
        static::assertFalse($formData->membership->boardStorage);
        static::assertSame('12345', $formData->membership->watersportFederationNumber);
        static::assertSame('Jan', $formData->personalInfo->firstName);
        static::assertSame('Vries', $formData->personalInfo->lastName);
        static::assertSame('jan@example.com', $formData->personalInfo->email);
        static::assertSame('NL91ABNA0417164300', $formData->paymentInfo->bankingAccountNumber);
        static::assertSame('ABNANL2A', $formData->paymentInfo->bankingBic);
        static::assertSame('J. de Vries', $formData->paymentInfo->bankingAccountHolderName);
        static::assertSame('2024-02-01T03:04:05+00:00', $formData->paymentInfo->mandateAcceptedDate?->format('c'));
    }

    public function test_welcome_advances_step_to_welcome(): void
    {
        $formData = FormData::createDefault();

        $updated = $formData->welcome();

        static::assertSame(Step::Welcome, $updated->step);
    }

    public function test_membership_advances_step_and_stores_data(): void
    {
        $formData = FormData::createDefault()->welcome();

        $membershipData = $formData->membership;
        $updated = $formData->membership($membershipData);

        static::assertSame(Step::Membership, $updated->step);
    }

    public function test_personal_info_advances_step_and_stores_data(): void
    {
        $formData = FormData::createDefault()
            ->welcome()
            ->membership(MembershipData::createDefault());

        $personalInfo = PersonalInfoData::createFromArray(self::PERSONAL_INFO_DATA);

        $updated = $formData->personalInfo($personalInfo);

        static::assertSame(Step::PersonalInfo, $updated->step);
        static::assertSame('Jan', $updated->personalInfo->firstName);
    }

    public function test_payment_info_advances_step_and_stores_data(): void
    {
        $formData = FormData::createDefault()
            ->welcome()
            ->membership(MembershipData::createDefault())
            ->personalInfo(PersonalInfoData::createDefault());

        $paymentInfo = PaymentInfoData::createFromArray(self::PAYMENT_INFO_DATA);

        $updated = $formData->paymentInfo($paymentInfo);

        static::assertSame(Step::PaymentInfo, $updated->step);
        static::assertSame('NL91ABNA0417164300', $updated->paymentInfo->bankingAccountNumber);
        static::assertSame('2024-02-01T03:04:05+00:00', $updated->paymentInfo->mandateAcceptedDate?->format('c'));
    }

    public function test_step_does_not_go_backwards(): void
    {
        $formData = FormData::createDefault()
            ->welcome()
            ->membership(MembershipData::createDefault());

        static::assertSame(Step::Membership, $formData->step);

        $updated = $formData->welcome();

        static::assertSame(Step::Membership, $updated->step);
    }

    public function test_is_step_disallowed_returns_true_when_skipping_steps(): void
    {
        $formData = FormData::createDefault()->welcome();

        static::assertTrue($formData->isStepDisallowed(Step::PersonalInfo));
        static::assertTrue($formData->isStepDisallowed(Step::PaymentInfo));
    }

    public function test_is_step_disallowed_returns_false_for_next_step(): void
    {
        $formData = FormData::createDefault()->welcome();

        static::assertFalse($formData->isStepDisallowed(Step::Membership));
    }

    public function test_to_array_and_create_roundtrip(): void
    {
        $original = FormData::createDefault()
            ->welcome()
            ->membership(MembershipData::createDefault())
            ->personalInfo(PersonalInfoData::createFromArray(self::PERSONAL_INFO_DATA))
            ->paymentInfo(PaymentInfoData::createFromArray(self::PAYMENT_INFO_DATA));

        $array = $original->toArray();
        $restored = FormData::create($array);

        static::assertSame($original->step->value, $restored->step->value);
        static::assertSame($original->membership->toArray(), $restored->membership->toArray());
        static::assertSame($original->personalInfo->toArray(), $restored->personalInfo->toArray());
        static::assertSame($original->paymentInfo->toArray(), $restored->paymentInfo->toArray());
    }
}
