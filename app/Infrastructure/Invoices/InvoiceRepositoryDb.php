<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices;

use App\Domain\Invoices\AppliedInvoiceWithLineIds;
use App\Domain\Invoices\ApplyInvoiceLines;
use App\Domain\Invoices\Billing\BillableItem;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\Invoices\InvoiceLineId;
use App\Domain\Invoices\InvoiceNumberGenerator;
use App\Domain\Invoices\InvoiceRepository;
use App\Domain\Invoices\InvoiceStatus;
use App\Domain\Invoices\NewInvoice;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;

final class InvoiceRepositoryDb implements InvoiceRepository
{
    private const float AMOUNT_TOLERANCE = 0.01;

    public function __construct(
        private InvoiceNumberGenerator $invoiceNumberGenerator,
    ) {}

    #[Override]
    public function create(NewInvoice $invoice): InvoiceId
    {
        DB::beginTransaction();

        $invoiceNumber = $this->invoiceNumberGenerator->generate();

        $member = Member::findOrFail($invoice->memberId->value);

        $model = Invoice::query()->create([
            'invoice_batch_id' => $invoice->batchId?->value,
            'recipient_email' => $member->email,
            'recipient_name' => $member->name,
            'recipient_address' => $member->address,
            'invoice_number' => $invoiceNumber,
            'member_id' => $invoice->memberId->value,
            'date' => $invoice->invoiceDate,
        ]);

        $model
            ->lines()
            ->createMany(
                array_map(
                    static fn (BillableItem $item) => [
                        'description' => $item->description,
                        'price' => $item->price->price,
                        'vat' => $item->price->vat,
                        'quantity' => $item->quantity,
                        'billable_item_id' => $item->id->value,
                        'cost_center_id' => $item->costCenterId->value,
                    ],
                    $invoice->items->items,
                ),
            );

        DB::commit();

        return new InvoiceId($model->id);
    }

    #[Override]
    public function applyLines(ApplyInvoiceLines $invoice): AppliedInvoiceWithLineIds
    {
        DB::beginTransaction();

        $invoiceNumber = $this->invoiceNumberGenerator->generate();

        $member = Member::findOrFail($invoice->memberId->value);

        $date = CarbonImmutable::create($invoice->date);
        $model = Invoice::query()
            ->whereBetween(
                'date',
                [
                    $date->startOfMonth(),
                    $date->endOfMonth(),
                ],
            )
            ->firstOrCreate(
                [
                    'status' => InvoiceStatus::Open,
                    'member_id' => $invoice->memberId->value,
                ],
                [
                    'recipient_email' => $member->email,
                    'recipient_name' => $member->name,
                    'recipient_address' => $member->address,
                    'invoice_number' => $invoiceNumber,
                    'date' => $invoice->date,
                ],
            );

        /** @var Collection<InvoiceLine> $lines */
        $lines = $model
            ->lines()
            ->createMany(
                array_map(
                    static fn (BillableItem $item) => [
                        'description' => $item->description,
                        'price' => $item->price->price,
                        'vat' => $item->price->vat,
                        'quantity' => $item->quantity,
                        'billable_item_id' => $item->id->value,
                        'cost_center_id' => $item->costCenterId->value,
                    ],
                    $invoice->items->items,
                ),
            );

        DB::commit();

        return new AppliedInvoiceWithLineIds(
            isNew: $model->wasRecentlyCreated,
            invoiceId: InvoiceId::create($model->id),
            lineIds: array_map(static fn (InvoiceLine $item) => InvoiceLineId::create($item->id), $lines->all()),
        );
    }

    #[Override]
    public function createCredit(InvoiceId $originalInvoiceId): InvoiceId
    {
        $original = Invoice::query()->with('lines')->findOrFail($originalInvoiceId->value);

        $invoiceNumber = $this->invoiceNumberGenerator->generate();

        $credit = DB::transaction(static function () use ($original, $invoiceNumber): Invoice {
            $creditInvoice = Invoice::query()->create([
                'member_id' => $original->member_id,
                'credit_invoice_id' => $original->id,
                'recipient_email' => $original->recipient_email,
                'recipient_name' => $original->recipient_name,
                'recipient_address' => $original->recipient_address,
                'invoice_number' => (string) $invoiceNumber,
                'date' => CarbonImmutable::now(),
                'status' => InvoiceStatus::Open,
            ]);

            $creditInvoice
                ->lines()
                ->createMany(
                    array_map(
                        static fn (InvoiceLine $line) => [
                            'description' => $line->description,
                            'price' => -$line->price,
                            'vat' => -$line->vat,
                            'quantity' => $line->quantity,
                            'billable_item_id' => $line->billable_item_id,
                            'cost_center_id' => $line->cost_center_id,
                        ],
                        $original->lines->all(),
                    ),
                );

            return $creditInvoice;
        });

        return InvoiceId::create($credit->id);
    }

    #[Override]
    public function markAsPaid(InvoiceIdList $ids): void
    {
        Invoice::query()
            ->whereIn('id', array_map(static fn (InvoiceId $id) => $id->value, $ids->ids))
            ->update(['status' => InvoiceStatus::Paid]);
    }

    #[Override]
    public function markAsDeclined(InvoiceIdList $ids): void
    {
        Invoice::query()
            ->whereIn('id', array_map(static fn (InvoiceId $id) => $id->value, $ids->ids))
            ->where('status', InvoiceStatus::Pending)
            ->update(['status' => InvoiceStatus::Declined]);
    }

    #[Override]
    public function markAsPending(InvoiceIdList $ids): void
    {
        Invoice::query()
            ->whereIn('id', array_map(static fn (InvoiceId $id) => $id->value, $ids->ids))
            ->update(['status' => InvoiceStatus::Pending]);
    }

    #[Override]
    public function findMatchingCredit(string $bankingAccountNumber, float $amount, DateTimeInterface $date): ?InvoiceId
    {
        $startDate = CarbonImmutable::instance($date)->subDays(30);
        $endDate = CarbonImmutable::instance($date)->addDays(30);

        $invoice = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Pending])
            ->whereBetween('date', [$startDate, $endDate])
            ->whereHas('member.paymentInformation', static function ($query) use ($bankingAccountNumber): void {
                $query->where('banking_account_number', $bankingAccountNumber);
            })
            ->orderByAmountProximity($amount)
            ->with('lines')
            ->first();

        if ($invoice === null) {
            return null;
        }

        if (abs($invoice->total->price - $amount) > self::AMOUNT_TOLERANCE) {
            return null;
        }

        return InvoiceId::create($invoice->id);
    }
}
