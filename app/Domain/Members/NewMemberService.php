<?php

declare(strict_types=1);

namespace App\Domain\Members;

use App\Domain\Members\Dto\NewMember;
use App\Domain\Members\Dto\NewMemberMembershipInformation;
use App\Domain\Members\Dto\NewMemberPaymentInformation;
use App\Domain\Members\Dto\NewMemberPersonalInformation;
use App\Domain\Registration\FormData;
use RuntimeException;

final readonly class NewMemberService
{
    public function __construct(
        private MemberRepository $memberRepository,
        private MembershipRepository $membershipRepository
    ) {
    }

    public function fromRegistration(FormData $formData): MemberId
    {
        if ($formData->paymentInfo->mandateAcceptedDate === null) {
            throw new RuntimeException('Unable to create new member without mandate accepted date.');
        }

        $newMember = new NewMember(
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

        return $this->memberRepository->newMember($newMember);
    }
}
