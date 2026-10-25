<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Payment;

use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Billing\Payment\Readiness\MandateReadiness;
use App\Domain\Billing\Payment\Readiness\MandateReadinessService;
use App\Domain\Billing\Payment\Readiness\MandateReadinessStatus;
use App\Domain\Members\MemberId;
use App\Models\PaymentInformation;
use Override;

final readonly class MandateReadinessServiceImpl implements MandateReadinessService
{
    /** @param list<MemberId> $memberIds */
    #[Override]
    public function forMembers(array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        $memberIdsAsInts = array_map(static fn (MemberId $id): int => $id->value, $memberIds);

        return PaymentInformation::query()
            ->whereIn('member_id', $memberIdsAsInts)
            ->get()
            ->mapWithKeys(static function (PaymentInformation $information): array {
                $memberId = (int) $information->getAttribute('member_id');

                return [
                    $memberId => new MandateReadiness(
                        status: MandateReadinessStatus::Active,
                        iban: (string) $information->getAttribute('banking_account_number'),
                        bic: (string) $information->getAttribute('banking_bic'),
                        mandateId: new MandateId(
                            MemberId::create($memberId),
                            PaymentInformationId::create((int) $information->getKey()),
                        ),
                        signedOn: $information->mandate_accepted_date,
                    ),
                ];
            })
            ->all();
    }
}
