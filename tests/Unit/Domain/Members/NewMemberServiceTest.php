<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\Dto\NewMember;
use App\Domain\Members\Dto\NewMemberMembershipInformation;
use App\Domain\Members\Dto\NewMemberPaymentInformation;
use App\Domain\Members\Dto\NewMemberPersonalInformation;
use App\Domain\Members\Events\NewMemberRegistration;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberNameFormatter;
use App\Domain\Members\MembershipId;
use App\Domain\Members\NewMemberService;
use App\Domain\Registration\FormData;
use App\Domain\Registration\MembershipData;
use App\Domain\Registration\PaymentInfoData;
use App\Domain\Registration\PersonalInfoData;
use RuntimeException;
use Tests\Unit\Laravel\EventDispatcherExpectation;
use Tests\UnitTestCase;

final class NewMemberServiceTest extends UnitTestCase
{
    private MemberRepositoryExpectation $memberRepo;

    private MembershipRepositoryExpectation $membershipRepo;

    private EventDispatcherExpectation $eventDispatcher;

    private NewMemberService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberRepo = MemberRepositoryExpectation::create();
        $this->membershipRepo = MembershipRepositoryExpectation::create();
        $this->eventDispatcher = EventDispatcherExpectation::create();

        $this->subject = new NewMemberService(
            $this->memberRepo->mock,
            $this->membershipRepo->mock,
            $this->eventDispatcher->mock,
        );
    }

    public function test_it_creates_a_new_member_from_registration_data(): void
    {
        $formData = $this->createFormData();
        $defaultMembershipId = MembershipId::create(5);
        $expectedMemberId = MemberId::create(42);

        $this->membershipRepo->expectsGetDefault($defaultMembershipId);
        $this->memberRepo->expectsNewMember(
            $this->buildExpectedNewMember($formData, $defaultMembershipId),
            $expectedMemberId,
        );

        $expectedRegistration = new NewMemberRegistration(
            $expectedMemberId,
            MemberNameFormatter::presentationName(
                $formData->personalInfo->firstName,
                $formData->personalInfo->infixName,
                $formData->personalInfo->lastName,
            ),
            $formData->personalInfo->email,
            $formData->membership
        );

        $this->eventDispatcher->expectsDispatchWith($expectedRegistration);

        $result = $this->subject->fromRegistration($formData);

        self::assertSame(42, $result->value);
    }

    public function test_it_throws_when_mandate_date_is_missing(): void
    {
        $this->expectException(RuntimeException::class);

        $this->eventDispatcher->expectsNotToDispatch();

        $formData = FormData::createDefault()
            ->welcome()
            ->membership(MembershipData::createDefault())
            ->personalInfo(PersonalInfoData::createFromArray([
                'firstName' => 'Jan',
                'infixName' => '',
                'lastName' => 'Vries',
                'email' => 'jan@example.com',
                'gender' => 'M',
                'birthdate' => '1990-01-15',
                'addressStreet' => 'Surfstrand',
                'addressHousenumber' => '2',
                'addressHousenumberAddition' => '',
                'addressPostalcode' => '1324CT',
                'addressCity' => 'Almere',
            ]))
            ->paymentInfo(PaymentInfoData::createFromArray([
                'bankingAccountNumber' => 'NL91ABNA0417164300',
                'bankingBic' => 'ABNANL2A',
                'bankingAccountHolderName' => 'J. de Vries',
                'mandateAcceptedDate' => null,
            ]));

        $this->subject->fromRegistration($formData);
    }

    private function createFormData(): FormData
    {
        return FormData::createDefault()
            ->welcome()
            ->membership(MembershipData::createDefault())
            ->personalInfo(PersonalInfoData::createFromArray([
                'firstName' => 'Jan',
                'infixName' => 'de',
                'lastName' => 'Vries',
                'email' => 'jan@example.com',
                'gender' => 'M',
                'birthdate' => '1990-01-15',
                'addressStreet' => 'Surfstrand',
                'addressHousenumber' => '2',
                'addressHousenumberAddition' => 'A',
                'addressPostalcode' => '1324CT',
                'addressCity' => 'Almere',
            ]))
            ->paymentInfo(PaymentInfoData::createFromArray([
                'bankingAccountNumber' => 'NL91ABNA0417164300',
                'bankingBic' => 'ABNANL2A',
                'bankingAccountHolderName' => 'J. de Vries',
                'mandateAcceptedDate' => '2024-02-01T03:04:05+00:00',
            ]));
    }

    private function buildExpectedNewMember(FormData $formData, MembershipId $defaultMembershipId): NewMember
    {
        return new NewMember(
            new NewMemberMembershipInformation($defaultMembershipId),
            new NewMemberPersonalInformation(
                $formData->personalInfo->firstName,
                $formData->personalInfo->infixName,
                $formData->personalInfo->lastName,
                $formData->personalInfo->gender,
                $formData->personalInfo->birthdate,
                $formData->personalInfo->email,
                $formData->personalInfo->addressStreet,
                $formData->personalInfo->addressHousenumber,
                $formData->personalInfo->addressHousenumberAddition,
                $formData->personalInfo->addressPostalcode,
                $formData->personalInfo->addressCity,
            ),
            new NewMemberPaymentInformation(
                $formData->paymentInfo->bankingAccountNumber,
                $formData->paymentInfo->bankingBic,
                $formData->paymentInfo->bankingAccountHolderName,
                $formData->paymentInfo->mandateAcceptedDate ?: throw new RuntimeException('Mandate date is missing'),
            ),
            $formData->toArray(),
        );
    }
}
