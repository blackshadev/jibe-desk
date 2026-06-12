<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property DateTimeInterface $mandate_accepted_date
 */
#[Guarded('id', 'uuid', 'updated_at', 'created_at')]
final class PaymentInformation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'payment_information';

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    protected static function booted(): void
    {
        static::creating(static function (PaymentInformation $paymentInformation) {
            if (empty($paymentInformation->uuid)) {
                $paymentInformation->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mandate_accepted_date' => 'date',
        ];
    }
}
