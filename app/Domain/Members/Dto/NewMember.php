<?php

declare(strict_types=1);

namespace App\Domain\Members\Dto;

final readonly class NewMember
{
    /** @param array<string, mixed> $registrationData */
    public function __construct(
        public NewMemberMembershipInformation $membershipInformation,
        public NewMemberPersonalInformation $personalInformation,
        public NewMemberPaymentInformation $paymentInformation,
        public array $registrationData = [],
    ) {
    }
}
