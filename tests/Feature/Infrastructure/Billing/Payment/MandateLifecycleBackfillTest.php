<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Billing\Payment;

use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Infrastructure\Billing\Payment\MandateLifecycleBackfill;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\SepaBatch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Override;
use Tests\FeatureTestCase;

final class MandateLifecycleBackfillTest extends FeatureTestCase
{
    private const string TABLE = 'payment_information_backfill_test';

    private MandateLifecycleBackfill $backfill;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create(self::TABLE, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->string('banking_account_number');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_collected_at')->nullable();
        });

        $this->backfill = new MandateLifecycleBackfill(self::TABLE);
    }

    #[Override]
    protected function tearDown(): void
    {
        Schema::dropIfExists(self::TABLE);

        parent::tearDown();
    }

    public function test_backfill_marks_soft_deleted_rows_as_revoked(): void
    {
        $this->insertMandate([
            'deleted_at' => '2026-01-15 00:00:00',
        ]);

        static::assertSame(1, $this->backfill->backfillRevocations());
        static::assertSame(
            '2026-01-15 00:00:00',
            CarbonImmutable::parse($this->mandateRow()->revoked_at)->format('Y-m-d H:i:s'),
        );
    }

    public function test_backfill_keeps_the_iban_of_a_soft_deleted_row(): void
    {
        $this->insertMandate([
            'banking_account_number' => 'NL91ABNA0417164300',
            'deleted_at' => '2026-01-15 00:00:00',
        ]);

        $this->backfill->backfillRevocations();

        static::assertSame('NL91ABNA0417164300', $this->mandateRow()->banking_account_number);
    }

    public function test_backfill_stamps_last_collected_at_from_completed_batches(): void
    {
        $member = $this->insertMandate();
        $this->createBatchInvoice($member, SepaBatchStatus::Completed, 10.00);

        static::assertSame(1, $this->backfill->backfillLastCollectedAt());
        static::assertSame(
            '2026-09-30',
            CarbonImmutable::parse($this->mandateRow()->last_collected_at)->format('Y-m-d'),
        );
    }

    public function test_backfill_stamps_last_collected_at_from_exported_batches(): void
    {
        $member = $this->insertMandate();
        $this->createBatchInvoice(
            $member,
            SepaBatchStatus::Pending,
            10.00,
            CarbonImmutable::parse('2026-10-01 12:00:00'),
        );

        static::assertSame(1, $this->backfill->backfillLastCollectedAt());
        static::assertNotNull($this->mandateRow()->last_collected_at);
    }

    public function test_backfill_ignores_open_and_pending_batches(): void
    {
        $member = $this->insertMandate();
        $this->createBatchInvoice($member, SepaBatchStatus::Open, 10.00);
        $this->createBatchInvoice($member, SepaBatchStatus::Pending, 10.00);

        static::assertSame(0, $this->backfill->backfillLastCollectedAt());
        static::assertNull($this->mandateRow()->last_collected_at);
    }

    public function test_backfill_ignores_credit_invoices(): void
    {
        $member = $this->insertMandate();
        $original = Invoice::factory()->create(['member_id' => $member]);

        $batch = SepaBatch::factory()->completed()->create([
            'sepa_transfer_date' => '2026-09-30',
        ]);
        $invoice = Invoice::factory()
            ->forBatch($batch)
            ->create([
                'member_id' => $member,
                'credit_invoice_id' => $original->id,
            ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'price' => -10.00,
            'quantity' => 1,
        ]);

        static::assertSame(0, $this->backfill->backfillLastCollectedAt());
    }

    public function test_backfill_ignores_a_refund_only_batch(): void
    {
        $member = $this->insertMandate();
        $this->createBatchInvoice($member, SepaBatchStatus::Completed, -10.00);

        static::assertSame(0, $this->backfill->backfillLastCollectedAt());
        static::assertNull($this->mandateRow()->last_collected_at);
    }

    public function test_backfill_does_not_overwrite_an_existing_last_collected_at(): void
    {
        $member = $this->insertMandate([
            'last_collected_at' => '2026-01-01 00:00:00',
        ]);
        $this->createBatchInvoice($member, SepaBatchStatus::Completed, 10.00);

        static::assertSame(0, $this->backfill->backfillLastCollectedAt());
        static::assertSame(
            '2026-01-01',
            CarbonImmutable::parse($this->mandateRow()->last_collected_at)->format('Y-m-d'),
        );
    }

    /** @param array<string, mixed> $attributes */
    private function insertMandate(array $attributes = []): int
    {
        $member = Member::factory()->createQuietly();

        DB::table(self::TABLE)->insert([
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
            'deleted_at' => null,
            'revoked_at' => null,
            'last_collected_at' => null,
            ...$attributes,
        ]);

        return $member->id;
    }

    private function mandateRow(): object
    {
        return DB::table(self::TABLE)->first();
    }

    private function createBatchInvoice(
        int $memberId,
        SepaBatchStatus $status,
        float $price,
        ?CarbonImmutable $exportedAt = null,
    ): Invoice {
        $batch = SepaBatch::factory()->create([
            'status' => $status,
            'sepa_transfer_date' => '2026-09-30',
            'exported_at' => $exportedAt,
        ]);

        $invoice = Invoice::factory()
            ->forBatch($batch)
            ->create(['member_id' => $memberId]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'price' => $price,
            'quantity' => 1,
        ]);

        return $invoice;
    }
}
