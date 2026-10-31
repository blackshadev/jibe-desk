<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Payment;

use App\Domain\Billing\Payment\MandateCorrection;
use App\Domain\Billing\Payment\MandateDetails;
use App\Domain\Billing\Payment\MandateRepository;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Members\MemberId;
use App\Domain\Members\PaymentMethod;
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
    public function create(MemberId $memberId, MandateDetails $details, ?PaymentMethod $switchMemberTo = null): PaymentInformationId
    {
        return DB::transaction(static function () use ($memberId, $details, $switchMemberTo): PaymentInformationId {
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

            if ($switchMemberTo !== null) {
                Member::query()
                    ->whereKey($memberId->value)
                    ->update(['payment_method' => $switchMemberTo]);
            }

            return PaymentInformationId::create($mandate->id);
        });
    }

    #[Override]
    public function update(PaymentInformationId $id, MandateCorrection $details): void
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
    public function stampCollectionsForBatch(SepaBatchId $batchId, DateTimeInterface $collectedAt): int
    {
        $collectedMemberIds = DB::table('invoices')
            ->join('invoice_lines', 'invoice_lines.invoice_id', '=', 'invoices.id')
            ->where('invoices.sepa_batch_id', $batchId->value)
            ->whereNull('invoices.credit_invoice_id')
            ->groupBy('invoices.id', 'invoices.member_id')
            ->havingRaw('SUM(invoice_lines.price * invoice_lines.quantity) > 0')
            ->select('invoices.member_id')
            ->distinct();

        return PaymentInformation::query()
            ->whereIn('member_id', $collectedMemberIds)
            ->whereNull('revoked_at')
            ->where(static function (Builder $query) use ($collectedAt): void {
                $query->whereNull('last_collected_at')
                    ->orWhere('last_collected_at', '<', $collectedAt);
            })
            ->update(['last_collected_at' => $collectedAt]);
    }
}
