<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Invoices\InvoiceBatchStatus;
use App\Models\InvoiceBatch;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Override;

/** @extends Factory<InvoiceBatch> */
final class InvoiceBatchFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        $invoiceDate = DateTimeImmutable::createFromInterface(fake()->dateTime('+14 days'));
        $sepaTransferDate = $invoiceDate->add(new DateInterval('P14D'));

        return [
            'invoice_date' => $invoiceDate,
            'sepa_transfer_date' => $sepaTransferDate->format('Y-m-d'),
            'status' => InvoiceBatchStatus::Open,
            'created_at' => fake()->dateTime(),
            'updated_at' => Carbon::now(),
        ];
    }

    public function pending(): self
    {
        return $this->state(['status' => InvoiceBatchStatus::Pending]);
    }

    public function completed(): self
    {
        return $this->state(['status' => InvoiceBatchStatus::Completed]);
    }
}
