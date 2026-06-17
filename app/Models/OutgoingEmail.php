<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Mail\OutgoingEmailStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
 * @property \Carbon\Carbon $queued_at
 * @property \Carbon\Carbon|null $sent_at
 */
#[Guarded([
    'id',
    'created_at',
    'updated_at',
])]
final class OutgoingEmail extends Model
{
    use HasFactory;

    /** @return MorphTo<Model, $this> */
    public function relatedModel(): MorphTo
    {
        return $this->morphTo();
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'status' => OutgoingEmailStatus::class,
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
