<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/** @property ExportReadinessReason $reason */
#[Guarded(['id', 'created_at', 'updated_at'])]
final class SepaExportOverride extends Model
{
    use HasFactory;

    /** @return BelongsTo<SepaBatch, $this> */
    public function sepaBatch(): BelongsTo
    {
        return $this->belongsTo(SepaBatch::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'reason' => ExportReadinessReason::class,
            'exported_at' => 'datetime',
        ];
    }
}
