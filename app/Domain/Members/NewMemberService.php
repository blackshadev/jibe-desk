<?php

declare(strict_types=1);

namespace App\Domain\Members;

use App\Domain\Members\Dto\NewMember;
use App\Domain\Members\Dto\NewMemberMembershipInformation;
use App\Domain\Members\Dto\NewMemberPaymentInformation;
use App\Domain\Members\Dto\NewMemberPersonalInformation;
use App\Domain\Members\Events\NewMemberRegistration;
use App\Domain\Registration\FormData;
use Illuminate\Contracts\Events\Dispatcher;
use RuntimeException;

final readonly class NewMemberService
{
    public function __construct(
        private MemberRepository $memberRepository,
        private MembershipRepository $membershipRepository,
        private Dispatcher $eventDispatcher,
    ) {}

    public function fromRegistration(FormData $formData): MemberId
    {
        $newMember = $this->toNewMember($formData);

        $memberId = $this->memberRepository->newMember($newMember);

        $event = $this->toNewMemberRegistrationEvent($memberId, $formData);
        $this->eventDispatcher->dispatch($event);

        return $memberId;
    }

    private function toNewMemberRegistrationEvent(MemberId $id, FormData $formData): NewMemberRegistration
    {
        return new NewMemberRegistration(
            memberId: $id,
            memberName: MemberNameFormatter::presentationName(
                $formData->personalInfo->firstName,
                $formData->personalInfo->infixName,
                $formData->personalInfo->lastName,
            ),
            memberEmail: $formData->personalInfo->email,
            membershipData: $formData->membership,
        );
    }

    private function toNewMember(FormData $formData): NewMember
    {
        if ($formData->paymentInfo->mandateAcceptedDate === null) {
            throw new RuntimeException('Unable to create new member without mandate accepted date.');
        }

        return new NewMember(
            new NewMemberMembershipInformation(
                $this->membershipRepository->getDefault(),
            ),
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
                $formData->paymentInfo->mandateAcceptedDate,
            ),
            $formData->toArray(),
        );
    }
}
