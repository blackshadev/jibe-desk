<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use App\Models\Invoice;
use App\Models\SepaBatch;
use App\Models\SepaExportOverride;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<SepaExportOverride> */
final class SepaExportOverrideFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [
            'sepa_batch_id' => SepaBatch::factory(),
            'invoice_id' => Invoice::factory(),
            'reason' => ExportReadinessReason::ExpiredMandate,
            'note' => fake()->sentence(),
            'exported_at' => now(),
            'exported_by' => null,
        ];
    }
}
