<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\MemberObjectObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

#[Fillable(['member_id', 'member_object_type_id', 'name', 'start_date', 'end_date', 'invoice_line_id'])]
#[ObservedBy(MemberObjectObserver::class)]
final class MemberObject extends Model
{
    use HasFactory;

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<MemberObjectType, $this> */
    public function memberObjectType(): BelongsTo
    {
        return $this->belongsTo(MemberObjectType::class);
    }

    /** @return BelongsTo<InvoiceLine, $this> */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
