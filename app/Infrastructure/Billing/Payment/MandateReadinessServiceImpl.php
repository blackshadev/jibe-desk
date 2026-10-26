<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Payment;

use App\Domain\Billing\Payment\MandateExpirationStatus;
use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Billing\Payment\Readiness\MandateReadiness;
use App\Domain\Billing\Payment\Readiness\MandateReadinessService;
use App\Domain\Billing\Payment\Readiness\MandateReadinessStatus;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Members\MemberId;
use App\Models\PaymentInformation;
use Override;

final readonly class MandateReadinessServiceImpl implements MandateReadinessService
{
    /** @param list<MemberId> $memberIds @return array<int, MandateReadiness> */
    #[Override]
    public function forMembers(array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        $rows = PaymentInformation::query()
            ->whereIn(
                'member_id',
                array_map(static fn (MemberId $id): int => $id->value, $memberIds),
            )
            ->orderByDesc('id')
            ->get()
            ->groupBy('member_id');

        $result = [];

        foreach ($rows as $memberId => $mandates) {
            $active = $mandates->first(
                static fn (PaymentInformation $mandate): bool => $mandate->revoked_at === null,
            );
            $mandate = $active ?? $mandates->first();

            if (!$mandate instanceof PaymentInformation) {
                continue;
            }

            $status = $active === null
                ? MandateReadinessStatus::Revoked
                : match ($mandate->expiration_status) {
                    MandateExpirationStatus::Active => MandateReadinessStatus::Active,
                    MandateExpirationStatus::Expired => MandateReadinessStatus::Expired,
                    MandateExpirationStatus::Revoked => MandateReadinessStatus::Revoked,
                };

            $result[(int) $memberId] = new MandateReadiness(
                status: $status,
                iban: $mandate->banking_account_number,
                bic: $mandate->banking_bic,
                mandateId: new MandateId(
                    MemberId::create((int) $memberId),
                    PaymentInformationId::create($mandate->id),
                ),
                signedOn: $mandate->mandate_accepted_date,
                expectedSequenceType: SequenceType::Recurring,
            );
        }

        return $result;
    }
}
