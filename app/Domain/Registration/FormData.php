<?php

declare(strict_types=1);

namespace App\Domain\Registration;

/**
 * @phpstan-import-type MembershipDataArray from MembershipData
 * @phpstan-import-type PersonalInfoDataArray from PersonalInfoData
 * @phpstan-import-type PaymentInfoDataArray from PaymentInfoData
 *
 * @phpstan-type FormDataArray array{ step?: integer, membership?: MembershipDataArray, personalInfo?: PersonalInfoDataArray, paymentInfo?: PaymentInfoDataArray }
 */

final class FormData
{
    private function __construct(
        public Step $step,
        public MembershipData $membership,
        public PersonalInfoData $personalInfo,
        public PaymentInfoData $paymentInfo,
    ) {
    }

    /** @param FormDataArray $data */
    public static function create(array $data): self
    {
        return new self(
            step: Step::tryFrom($data['step'] ?? Step::Initial->value) ?? Step::Initial,
            membership: MembershipData::createFromArray($data['membership'] ?? []),
            personalInfo: PersonalInfoData::createFromArray($data['personalInfo'] ?? []),
            paymentInfo: PaymentInfoData::createFromArray($data['paymentInfo'] ?? []),
        );
    }

    public static function createDefault(): self
    {
        return new self(
            step: Step::Initial,
            membership: MembershipData::createDefault(),
            personalInfo: PersonalInfoData::createDefault(),
            paymentInfo: PaymentInfoData::createDefault(),
        );
    }

    public function isStepDisallowed(Step $step): bool
    {
        return $this->step->value + 1 < $step->value;
    }

    public function welcome(): self
    {
        return new self(
            $this->maxStep(Step::Welcome),
            $this->membership,
            $this->personalInfo,
            $this->paymentInfo,
        );
    }

    /** @return FormDataArray */
    public function toArray(): array
    {
        return [
            'step' => $this->step->value,
            'membership' => $this->membership->toArray(),
            'personalInfo' => $this->personalInfo->toArray(),
            'paymentInfo' => $this->paymentInfo->toArray(),
        ];
    }

    public function membership(MembershipData $membership): self
    {
        return new self(
            $this->maxStep(Step::Membership),
            $membership,
            $this->personalInfo,
            $this->paymentInfo,
        );
    }

    public function personalInfo(PersonalInfoData $personalInfo): self
    {
        return new self(
            $this->maxStep(Step::PersonalInfo),
            $this->membership,
            $personalInfo,
            $this->paymentInfo,
        );
    }

    public function paymentInfo(PaymentInfoData $paymentInfo): self
    {
        return new self(
            $this->maxStep(Step::PaymentInfo),
            $this->membership,
            $this->personalInfo,
            $paymentInfo,
        );
    }

    private function maxStep(Step $step): Step
    {
        if ($this->step->value < $step->value) {
            return $step;
        }

        return $this->step;
    }
}
