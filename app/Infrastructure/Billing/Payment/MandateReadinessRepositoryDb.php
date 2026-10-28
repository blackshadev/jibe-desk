<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Payment;

use App\Domain\Billing\Payment\MandateExpirationStatus;
use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\MandateSequenceResolver;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Billing\Payment\Readiness\MandateReadiness;
use App\Domain\Billing\Payment\Readiness\MandateReadinessRepository;
use App\Domain\Billing\Payment\Readiness\MandateReadinessStatus;
use App\Domain\Members\MemberId;
use App\Domain\Members\PaymentMethod;
use App\Models\Member;
use App\Models\PaymentInformation;
use Override;

final readonly class MandateReadinessRepositoryDb implements MandateReadinessRepository
{
    public function __construct(
        private MandateSequenceResolver $sequenceResolver,
    ) {}

    /** @param list<MemberId> $memberIds @return array<int, MandateReadiness> */
    #[Override]
    public function forMembers(array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        return Member::query()
            ->whereIn('id', array_map(static fn (MemberId $memberId): int => $memberId->value, $memberIds))
            ->with('currentPaymentInformation')
            ->get()
            ->mapWithKeys(function (Member $member): array {
                $mandate = $member->currentPaymentInformation;

                if (!$mandate instanceof PaymentInformation) {
                    return [$member->id => MandateReadiness::missing($member->payment_method)];
                }

                return [$member->id => $this->readiness($mandate, $member->payment_method)];
            })
            ->all();
    }

    private function readiness(PaymentInformation $mandate, PaymentMethod $paymentMethod): MandateReadiness
    {
        return new MandateReadiness(
            status: match ($mandate->expiration_status) {
                MandateExpirationStatus::Active => MandateReadinessStatus::Active,
                MandateExpirationStatus::Expired => MandateReadinessStatus::Expired,
                MandateExpirationStatus::Revoked => MandateReadinessStatus::Revoked,
            },
            iban: $mandate->banking_account_number,
            bic: $mandate->banking_bic,
            mandateId: new MandateId(
                MemberId::create($mandate->member_id),
                PaymentInformationId::create($mandate->id),
            ),
            signedOn: $mandate->mandate_accepted_date,
            expectedSequenceType: $this->sequenceResolver->resolve($mandate->last_collected_at),
            paymentMethod: $paymentMethod,
        );
    }
}
