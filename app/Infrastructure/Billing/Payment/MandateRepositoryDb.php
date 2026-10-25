<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Payment;

use App\Domain\Billing\Payment\MandateCorrection;
use App\Domain\Billing\Payment\MandateDetails;
use App\Domain\Billing\Payment\MandateRepository;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Billing\Payment\PaymentInformationIdList;
use App\Domain\Members\MemberId;
use App\Models\Member;
use App\Models\PaymentInformation;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Override;

final class MandateRepositoryDb implements MandateRepository
{
    #[Override]
    public function register(MemberId $memberId, MandateDetails $details): PaymentInformationId
    {
        return DB::transaction(static function () use ($memberId, $details): PaymentInformationId {
            Member::query()
                ->whereKey($memberId->value)
                ->lockForUpdate()
                ->firstOrFail();

            PaymentInformation::query()
                ->where('member_id', $memberId->value)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => CarbonImmutable::now()]);

            $mandate = PaymentInformation::query()->create([
                'member_id' => $memberId->value,
                'banking_account_number' => $details->iban,
                'banking_bic' => $details->bic,
                'banking_account_holder_name' => $details->accountHolderName,
                'mandate_accepted_date' => $details->signedAt,
                'revoked_at' => null,
                'last_collected_at' => null,
            ]);

            return PaymentInformationId::create($mandate->id);
        });
    }

    #[Override]
    public function correctDetails(PaymentInformationId $id, MandateCorrection $details): void
    {
        PaymentInformation::query()
            ->whereKey($id->value)
            ->whereNull('revoked_at')
            ->update([
                'banking_bic' => $details->bic,
                'banking_account_holder_name' => $details->accountHolderName,
            ]);
    }

    #[Override]
    public function revoke(PaymentInformationId $id, DateTimeInterface $revokedAt): void
    {
        PaymentInformation::query()
            ->whereKey($id->value)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]);
    }

    #[Override]
    public function stampCollections(PaymentInformationIdList $ids, DateTimeInterface $collectedAt): int
    {
        if ($ids->ids === []) {
            return 0;
        }

        return PaymentInformation::query()
            ->whereIn('id', $ids->values())
            ->where(static function (Builder $query) use ($collectedAt): void {
                $query->whereNull('last_collected_at')
                    ->orWhere('last_collected_at', '<', $collectedAt);
            })
            ->update(['last_collected_at' => $collectedAt]);
    }
}
