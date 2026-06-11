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
    public function test_create_default_has_initial_step_and_empty_data(): void
    {
        $formData = FormData::createDefault();

        self::assertSame(Step::Initial, $formData->step);
        self::assertSame('', $formData->membership->watersportFederationNumber);
        self::assertSame('', $formData->personalInfo->firstName);
        self::assertSame('', $formData->paymentInfo->bankingAccountNumber);
    }

    public function test_create_from_array_hydrates_all_data(): void
    {
        $formData = FormData::create([
            'step' => Step::Membership->value,
            'membership' => [
                'regularWindsurfingLessons' => true,
                'rtc' => false,
                'clubhouseAccess' => true,
                'boardStorage' => false,
                'watersportFederationNumber' => '12345',
            ],
            'personalInfo' => [
                'firstName' => 'Jan',
                'lastName' => 'Vries',
                'email' => 'jan@example.com',
            ],
            'paymentInfo' => [
                'bankingAccountNumber' => 'NL91ABNA0417164300',
                'bankingBic' => 'ABNANL2A',
                'bankingAccountHolderName' => 'J. de Vries',
                'mandateAcceptedDate' => '2024-02-01T03:04:05+00:00',
            ],
        ]);

        self::assertSame(Step::Membership, $formData->step);
        self::assertTrue($formData->membership->regularWindsurfingLessons);
        self::assertFalse($formData->membership->rtc);
        self::assertTrue($formData->membership->clubhouseAccess);
        self::assertFalse($formData->membership->boardStorage);
        self::assertSame('12345', $formData->membership->watersportFederationNumber);
        self::assertSame('Jan', $formData->personalInfo->firstName);
        self::assertSame('Vries', $formData->personalInfo->lastName);
        self::assertSame('jan@example.com', $formData->personalInfo->email);
        self::assertSame('NL91ABNA0417164300', $formData->paymentInfo->bankingAccountNumber);
        self::assertSame('ABNANL2A', $formData->paymentInfo->bankingBic);
        self::assertSame('J. de Vries', $formData->paymentInfo->bankingAccountHolderName);
        self::assertSame('2024-02-01T03:04:05+00:00', $formData->paymentInfo->mandateAcceptedDate->format('c'));
    }

    public function test_welcome_advances_step_to_welcome(): void
    {
        $formData = FormData::createDefault();

        $updated = $formData->welcome();

        self::assertSame(Step::Welcome, $updated->step);
    }

    public function test_membership_advances_step_and_stores_data(): void
    {
        $formData = FormData::createDefault()->welcome();

        $membershipData = $formData->membership;
        $updated = $formData->membership($membershipData);

        self::assertSame(Step::Membership, $updated->step);
    }

    public function test_personal_info_advances_step_and_stores_data(): void
    {
        $formData = FormData::createDefault()
            ->welcome()
            ->membership(MembershipData::createDefault());

        $personalInfo = PersonalInfoData::createFromArray([
            'firstName' => 'Jan',
            'lastName' => 'Vries',
        ]);

        $updated = $formData->personalInfo($personalInfo);

        self::assertSame(Step::PersonalInfo, $updated->step);
        self::assertSame('Jan', $updated->personalInfo->firstName);
    }

    public function test_payment_info_advances_step_and_stores_data(): void
    {
        $formData = FormData::createDefault()
            ->welcome()
            ->membership(MembershipData::createDefault())
            ->personalInfo(PersonalInfoData::createDefault());

        $paymentInfo = PaymentInfoData::createFromArray([
            'bankingAccountNumber' => 'NL91ABNA0417164300',
            'mandateAcceptedDate' => '2024-02-01T03:04:05+00:00',
        ]);

        $updated = $formData->paymentInfo($paymentInfo);

        self::assertSame(Step::PaymentInfo, $updated->step);
        self::assertSame('NL91ABNA0417164300', $updated->paymentInfo->bankingAccountNumber);
        self::assertSame('2024-02-01T03:04:05+00:00', $updated->paymentInfo->mandateAcceptedDate->format('c'));
    }

    public function test_step_does_not_go_backwards(): void
    {
        $formData = FormData::createDefault()
            ->welcome()
            ->membership(MembershipData::createDefault());

        self::assertSame(Step::Membership, $formData->step);

        $updated = $formData->welcome();

        self::assertSame(Step::Membership, $updated->step);
    }

    public function test_is_step_disallowed_returns_true_when_skipping_steps(): void
    {
        $formData = FormData::createDefault()->welcome();

        self::assertTrue($formData->isStepDisallowed(Step::PersonalInfo));
        self::assertTrue($formData->isStepDisallowed(Step::PaymentInfo));
    }

    public function test_is_step_disallowed_returns_false_for_next_step(): void
    {
        $formData = FormData::createDefault()->welcome();

        self::assertFalse($formData->isStepDisallowed(Step::Membership));
    }

    public function test_to_array_and_create_roundtrip(): void
    {
        $original = FormData::createDefault()
            ->welcome()
            ->membership(MembershipData::createDefault())
            ->personalInfo(PersonalInfoData::createFromArray([
                'firstName' => 'Jan',
                'lastName' => 'Vries',
                'email' => 'jan@example.com',
            ]))
            ->paymentInfo(PaymentInfoData::createFromArray([
                'bankingAccountNumber' => 'NL91ABNA0417164300',
                'bankingBic' => 'ABNANL2A',
                'bankingAccountHolderName' => 'J. de Vries',
                'mandateAccepted' => true,
            ]));

        $array = $original->toArray();
        $restored = FormData::create($array);

        self::assertSame($original->step->value, $restored->step->value);
        self::assertSame($original->membership->toArray(), $restored->membership->toArray());
        self::assertSame($original->personalInfo->toArray(), $restored->personalInfo->toArray());
        self::assertSame($original->paymentInfo->toArray(), $restored->paymentInfo->toArray());
    }
}
