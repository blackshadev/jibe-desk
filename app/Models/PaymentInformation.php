<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Billing\Payment\MandateExpirationStatus;
use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Members\MemberId;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * A SEPA mandate: one row per signed authorisation. A member has many over
 * time and at most one active row (revoked_at is null).
 *
 * @property int $id
 * @property int $member_id
 * @property string $banking_account_number
 * @property string $banking_bic
 * @property string $banking_account_holder_name
 * @property DateTimeInterface $mandate_accepted_date
 * @property null|DateTimeInterface $revoked_at
 * @property null|DateTimeInterface $last_collected_at
 * @property-read string $mandate_reference
 * @property-read CarbonImmutable $expires_at
 * @property-read MandateExpirationStatus $expiration_status
 */
#[Guarded('id', 'updated_at', 'created_at')]
final class PaymentInformation extends Model
{
    use HasFactory;

    /**
     * A mandate lapses when it has not been used for this many months.
     */
    public const int MONTHS_WITHOUT_COLLECTION = 36;

    #[Override]
    protected $table = 'payment_information';

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return Attribute<string, never> */
    protected function mandateReference(): Attribute
    {
        return Attribute::get(fn (): string => new MandateId(
            MemberId::create($this->member_id),
            PaymentInformationId::create($this->id),
        )->value);
    }

    /** @return Attribute<CarbonImmutable, never> */
    protected function expiresAt(): Attribute
    {
        return Attribute::get(function (): CarbonImmutable {
            $expiresAt = $this->last_collected_at;

            if ($expiresAt === null) {
                $expiresAt = $this->mandate_accepted_date;
            }

            return CarbonImmutable::instance($expiresAt)
                ->addMonthsNoOverflow(self::MONTHS_WITHOUT_COLLECTION);
        });
    }

    /** @return Attribute<MandateExpirationStatus, never> */
    protected function expirationStatus(): Attribute
    {
        return Attribute::get($this->determineExpirationStatus(...));
    }

    private function determineExpirationStatus(): MandateExpirationStatus
    {
        if ($this->revoked_at !== null) {
            return MandateExpirationStatus::Revoked;
        }

        return $this->expires_at->isFuture()
            ? MandateExpirationStatus::Active
            : MandateExpirationStatus::Expired;
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'mandate_accepted_date' => 'date',
            'revoked_at' => 'datetime',
            'last_collected_at' => 'datetime',
        ];
    }
}
