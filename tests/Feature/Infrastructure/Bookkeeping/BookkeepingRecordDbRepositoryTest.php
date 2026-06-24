<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Bookkeeping;

use App\Domain\Invoices\InvoiceBatchId;
use App\Infrastructure\Bookkeeping\BookkeepingRecordDbRepository;
use App\Models\BookkeepingRecord;
use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\InvoiceLine;
use Override;
use Tests\FeatureTestCase;

final class BookkeepingRecordDbRepositoryTest extends FeatureTestCase
{
    private BookkeepingRecordDbRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new BookkeepingRecordDbRepository();
    }

    public function test_get_results_for_year_combines_budget_and_bookkeeping(): void
    {
        $costCenter = CostCenter::factory()->create();
        CostCenterBudget::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
            'starting_amount' => 5000,
        ]);
        BookkeepingRecord::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
            'amount_price' => 1200,
            'amount_vat' => 252,
        ]);

        $results = $this->repository->getResultsForYear(2026);

        static::assertCount(1, $results);
        $result = $results[0];
        static::assertSame($costCenter->id, $result->costCenterId->value);
        static::assertSame(5000.0, $result->startingAmount);
        static::assertSame(1200.0, $result->totalBookkeeping->price);
        static::assertSame(252.0, $result->totalBookkeeping->vat);
        static::assertSame(6200.0, $result->result()->price);
    }

    public function test_get_results_for_year_without_budget_defaults_to_zero(): void
    {
        $costCenter = CostCenter::factory()->create();
        BookkeepingRecord::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
            'amount_price' => 300,
            'amount_vat' => 63,
        ]);

        $results = $this->repository->getResultsForYear(2026);

        static::assertSame(0.0, $results[0]->startingAmount);
        static::assertSame(300.0, $results[0]->result()->price);
    }

    public function test_get_results_for_year_excludes_other_years(): void
    {
        $costCenter = CostCenter::factory()->create();
        BookkeepingRecord::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2025,
            'amount_price' => 999,
        ]);
        BookkeepingRecord::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
            'amount_price' => 100,
        ]);

        $results = $this->repository->getResultsForYear(2026);

        static::assertSame(100.0, $results[0]->totalBookkeeping->price);
    }

    public function test_create_for_batch_creates_records_per_cost_center(): void
    {
        $costCenterA = CostCenter::factory()->create();
        $costCenterB = CostCenter::factory()->create();
        $batch = InvoiceBatch::factory()->create(['invoice_date' => '2026-06-15']);

        $invoice = Invoice::factory()
            ->has(InvoiceLine::factory()->state(['cost_center_id' => $costCenterA->id, 'price' => 100, 'vat' => 21, 'quantity' => 2]), 'lines')
            ->has(InvoiceLine::factory()->state(['cost_center_id' => $costCenterB->id, 'price' => 50, 'vat' => 10.5, 'quantity' => 1]), 'lines')
            ->create([
                'invoice_batch_id' => $batch->id,
                'status' => 'pending',
            ]);

        $this->repository->createForBatch(InvoiceBatchId::create($batch->id));

        $records = BookkeepingRecord::query()->where('reference_type', Invoice::class)->get();

        static::assertCount(2, $records);
        static::assertTrue($records->contains(static fn ($r) => $r->cost_center_id === $costCenterA->id && (float) $r->amount_price === 200.0));
        static::assertTrue($records->contains(static fn ($r) => $r->cost_center_id === $costCenterB->id && (float) $r->amount_price === 50.0));
        static::assertSame(2026, $records->first()->year);
    }

    public function test_create_for_batch_skips_invoices_already_in_bookkeeping_records(): void
    {
        $costCenter = CostCenter::factory()->create();
        $batch = InvoiceBatch::factory()->create(['invoice_date' => '2026-06-15']);

        $invoice = Invoice::factory()
            ->has(InvoiceLine::factory()->state(['cost_center_id' => $costCenter->id, 'price' => 100, 'vat' => 21, 'quantity' => 1]), 'lines')
            ->create([
                'invoice_batch_id' => $batch->id,
                'status' => 'pending',
            ]);

        BookkeepingRecord::factory()->create([
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
            'amount_price' => 100,
            'amount_vat' => 21,
        ]);

        $this->repository->createForBatch(InvoiceBatchId::create($batch->id));

        $records = BookkeepingRecord::query()->where('reference_type', Invoice::class)->get();

        static::assertCount(1, $records);
        static::assertSame(100.0, (float) $records->first()->amount_price);
    }

    public function test_create_for_batch_no_pending_invoices_creates_nothing(): void
    {
        $batch = InvoiceBatch::factory()->create();

        $this->repository->createForBatch(InvoiceBatchId::create($batch->id));

        static::assertSame(0, BookkeepingRecord::query()->count());
    }
}
