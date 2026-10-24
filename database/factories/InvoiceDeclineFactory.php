<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceDecline;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<InvoiceDecline> */
final class InvoiceDeclineFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'sepa_batch_id' => null,
            'bank_transaction_id' => null,
            'declined_at' => now(),
            'note' => null,
        ];
    }
}
