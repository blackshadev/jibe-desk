<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\BankingTransaction;
use App\Models\BookkeepingRecord;
use Tests\FeatureTestCase;

final class BankingTransactionTest extends FeatureTestCase
{
    public function test_unmatched_amount_returns_full_amount_when_no_bookkeeping_records(): void
    {
        $transaction = BankingTransaction::factory()
            ->createQuietly(['amount' => 150.500]);

        $result = $transaction->unmatched_amount;

        static::assertSame(150.5, $result);
    }

    public function test_unmatched_amount_subtracts_sum_of_bookkeeping_record_totals(): void
    {
        $transaction = BankingTransaction::factory()
            ->createQuietly(['amount' => 200.000]);

        BookkeepingRecord::factory()->createQuietly([
            'banking_transaction_id' => $transaction->id,
            'amount_price' => 50.000,
            'amount_vat' => 10.500,
        ]);

        BookkeepingRecord::factory()->createQuietly([
            'banking_transaction_id' => $transaction->id,
            'amount_price' => 25.000,
            'amount_vat' => 5.250,
        ]);

        $result = $transaction->unmatched_amount;

        // 200.0 - (50.0 + 25.0) = 200.0 - 75.0 = 125.0
        static::assertSame(125.0, $result);
    }

    public function test_unmatched_amount_returns_zero_when_fully_matched(): void
    {
        $transaction = BankingTransaction::factory()
            ->createQuietly(['amount' => 100.000]);

        BookkeepingRecord::factory()->createQuietly([
            'banking_transaction_id' => $transaction->id,
            'amount_price' => 60.000,
            'amount_vat' => 0.000,
        ]);

        BookkeepingRecord::factory()->createQuietly([
            'banking_transaction_id' => $transaction->id,
            'amount_price' => 40.000,
            'amount_vat' => 0.000,
        ]);

        $result = $transaction->unmatched_amount;

        static::assertSame(0.0, $result);
    }

    public function test_unmatched_amount_handles_negative_transaction_amount(): void
    {
        $transaction = BankingTransaction::factory()
            ->createQuietly(['amount' => -50.000]);

        BookkeepingRecord::factory()->createQuietly([
            'banking_transaction_id' => $transaction->id,
            'amount_price' => 20.000,
            'amount_vat' => 4.200,
        ]);

        $result = $transaction->unmatched_amount;

        // -50.0 - 20.0 = -50.0 - 24.2 = -70.0
        static::assertSame(-70.0, $result);
    }
}
