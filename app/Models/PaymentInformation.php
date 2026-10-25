<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Billing\Payment\MandateExpiry;
use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\MandateState;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Members\MemberId;
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
 */
#[Guarded('id', 'updated_at', 'created_at')]
final class PaymentInformation extends Model
{
    use HasFactory;

    #[Override]
    protected $table = 'payment_information';

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function state(MandateExpiry $expiry): MandateState
    {
        return $expiry->state(
            signedAt: $this->mandate_accepted_date,
            lastCollectedAt: $this->last_collected_at,
            revokedAt: $this->revoked_at,
        );
    }

    /** @return Attribute<string, never> */
    protected function mandateReference(): Attribute
    {
        return Attribute::get(fn (): string => new MandateId(
            MemberId::create($this->member_id),
            PaymentInformationId::create($this->id),
        )->value);
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
