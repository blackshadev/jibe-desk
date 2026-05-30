<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'member_object_type_id', 'name', 'start_date', 'end_date'])]
final class MemberObject extends Model
{
    use HasFactory;

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function memberObjectType(): BelongsTo
    {
        return $this->belongsTo(MemberObjectType::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
