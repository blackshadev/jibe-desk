# Invoice Batch: `invoice_date` + `sepa_transfer_date`

**Date**: 2026-10-06

## Summary

### Current situation

- `invoice_batches` already has a required `invoice_date` column (migration `2026_05_19_193924_create_invoice_batches_table.php`) plus a `status` column.
- The SEPA exporter (`SepaExportServiceImpl`) uses the batch's `invoice_date` as the `dueDate` for both the direct-debit and credit-transfer `PaymentInformation` blocks, via `InvoiceBatchRepository::getBatchDate()`.
- The invoice email (`InvoiceMailRepositoryDb`) currently uses `invoiceBatch->invoice_date` as the SEPA collection date shown to members ("geïncasseerd op …").
- When a batch is closed, `InvoiceBatchRepositoryDb::markInvoicesAsPending()` only flips invoices `open → pending`; it does **not** touch the invoice `date`.
- Batches are created three ways: the monthly console command (`GenerateInvoiceBatchCommand`), the Filament create page (`CreateInvoiceBatch`), and via `InvoiceBatchService::createBatch()`.

### Planned changes

1. Add a required `sepa_transfer_date` column to `invoice_batches` (backfilled from `invoice_date` for existing rows).
2. Thread `sepaTransferDate` through the domain value object, service, repository, console command, and Filament create page.
3. Use `sepa_transfer_date` as the `dueDate` in the SEPA exporter.
4. Use `sepa_transfer_date` as the collection date in the member invoice email.
5. Override the invoice `date` with the batch's `invoice_date` when invoices transition `open → pending` (inside `markInvoicesAsPending()`).

## Detailed plan

### Migration

Create `database/migrations/2026_10_06_000000_add_sepa_transfer_date_to_invoice_batches_table.php`.

The column is added nullable first, backfilled from `invoice_date` for any existing rows, then tightened to non-null so it matches `invoice_date`'s constraint.

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('invoice_batches', static function (Blueprint $table): void {
            $table->date('sepa_transfer_date')->nullable();
        });

        DB::table('invoice_batches')
            ->whereNull('sepa_transfer_date')
            ->update(['sepa_transfer_date' => DB::raw('invoice_date')]);

        Schema::table('invoice_batches', static function (Blueprint $table): void {
            $table->date('sepa_transfer_date')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_batches', static function (Blueprint $table): void {
            $table->dropColumn('sepa_transfer_date');
        });
    }
};
```

### Model

`app/Models/InvoiceBatch.php` — register the new column as fillable, cast it, and document it.

```php
/**
 * @property InvoiceBatchStatus $status
 * @property DateTimeInterface $invoice_date
 * @property DateTimeInterface $sepa_transfer_date
 */
#[Fillable(['invoice_date', 'sepa_transfer_date', 'status'])]
final class InvoiceBatch extends Model
{
    // ...

    #[Override]
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'sepa_transfer_date' => 'date',
            'status' => InvoiceBatchStatus::class,
        ];
    }
}
```

### Factory

`database/factories/InvoiceBatchFactory.php` — provide a default so existing feature tests keep working now that the column is non-nullable.

```php
public function definition(): array
{
    $invoiceDate = fake()->dateTime('+14 days');
    $sepaTransferDate = fake()->dateTimeBetween($invoiceDate,  $invoiceDate->add(new DateInterval("P14D"));
    
    return [
        'invoice_date' => $invoiceDate,
        'sepa_transfer_date' => $sepaTransferDate->date(),
        'status' => InvoiceBatchStatus::Open,
        'created_at' => fake()->dateTime(),
        'updated_at' => Carbon::now(),
    ];
}
```

### Domain value object

`app/Domain/Invoices/InvoiceBatch.php` — add the SEPA transfer date.

```php
final readonly class InvoiceBatch
{
    public function __construct(
        public DateTimeInterface $invoiceDate,
        public DateTimeInterface $sepaTransferDate,
    ) {}
}
```

### Domain service

`app/Domain/Invoices/InvoiceBatchService.php`:

```php
public function createBatch(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate): InvoiceBatchId;
```

`app/Domain/Invoices/InvoiceBatchServiceImpl.php`:

```php
#[Override]
public function createBatch(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate): InvoiceBatchId
{
    return $this->batchRepository->create($invoiceDate, $sepaTransferDate, InvoiceBatchStatus::Open);
}
```

### Repository interface

`app/Domain/Invoices/InvoiceBatchRepository.php`:

- Extend `create()` with the SEPA transfer date.
- Rename `getBatchDate()` to `getSepaTransferDate()` (it is now only consumed by the SEPA exporter, and its meaning changes from invoice date to SEPA transfer date).

```php
public function create(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate, InvoiceBatchStatus $status): InvoiceBatchId;

// ...

public function getSepaTransferDate(InvoiceBatchId $batchId): DateTimeInterface;
```

### Repository implementation

`app/Infrastructure/Invoices/InvoiceBatchRepositoryDb.php`:

```php
#[Override]
public function create(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate, InvoiceBatchStatus $status): InvoiceBatchId
{
    $model = InvoiceBatch::query()->create([
        'invoice_date' => $invoiceDate,
        'sepa_transfer_date' => $sepaTransferDate,
        'status' => $status,
    ]);

    return InvoiceBatchId::create($model->id);
}
```

Override the invoice date when invoices move `open → pending`:

```php
#[Override]
public function markInvoicesAsPending(InvoiceBatchId $batchId): void
{
    $batch = InvoiceBatch::findOrFail($batchId->value);

    Invoice::query()
        ->where('invoice_batch_id', $batchId->value)
        ->where('status', InvoiceStatus::Open)
        ->update([
            'status' => InvoiceStatus::Pending,
            'date' => $batch->invoice_date,
        ]);
}
```

Replace `getBatchDate()` with `getSepaTransferDate()`:

```php
#[Override]
public function getSepaTransferDate(InvoiceBatchId $batchId): DateTimeInterface
{
    /** @var InvoiceBatch $batch */
    $batch = InvoiceBatch::findOrFail($batchId->value);
    return $batch->sepa_transfer_date;
}
```

### SEPA exporter

`app/Infrastructure/Invoices/SepaExportServiceImpl.php` — read the due date from the new method:

```php
#[Override]
public function export(InvoiceBatchId $batchId): SepaExport
{
    $dueDate = $this->batchRepository->getSepaTransferDate($batchId);

    // ... rest unchanged; both addPaymentInfo() calls still use 'dueDate' => $dueDate
}
```

### Invoice mail

`app/Infrastructure/Invoices/InvoiceMailRepositoryDb.php` — the collection date in the member email becomes the SEPA transfer date:

```php
sepaTransferDate: $invoice->member?->paymentInformation?->mandate_accepted_date !== null ? $invoice->invoiceBatch?->sepa_transfer_date : null,
```

### Console command

`app/Console/Commands/GenerateInvoiceBatchCommand.php` — accept an optional SEPA transfer date and default it to 14 days after the invoice date (SEPA Core Direct Debit pre-notification; adjust if the club's terms differ).

```php
#[Signature('app:generate-invoice-batch {date?} {sepaTransferDate?}')]
#[Description('Generate an invoice batch for a given date')]
final class GenerateInvoiceBatchCommand extends Command
{
    public function handle(InvoiceBatchGenerator $invoiceBatchGenerator): void
    {
        $date = $this->parseDate();

        $command = new InvoiceBatch(
            invoiceDate: $date,
            sepaTransferDate: $this->parseSepaTransferDate($date),
        );

        $invoiceBatchGenerator->generate($command);
    }

    private function parseDate(): DateTimeInterface
    {
        if ($this->argument('date')) {
            return CarbonImmutable::createFromFormat('Y-m-d', $this->argument('date'));
        }

        return CarbonImmutable::now()->startOfDay();
    }

    private function parseSepaTransferDate(DateTimeInterface $invoiceDate): DateTimeInterface
    {
        if ($this->argument('sepaTransferDate')) {
            return CarbonImmutable::createFromFormat('Y-m-d', $this->argument('sepaTransferDate'));
        }

        return CarbonImmutable::instance($invoiceDate)->addDays(14);
    }
}
```

### Generator

`app/Domain/Invoices/InvoiceBatchGeneratorImpl.php` — pass both dates to `createBatch()`:

```php
$batchId = $this->batchService->createBatch($invoiceBatch->invoiceDate, $invoiceBatch->sepaTransferDate);
```

### Filament form

`app/Filament/Admin/Resources/InvoiceBatches/Schemas/InvoiceBatchForm.php` — add the new date picker alongside `invoice_date`:

```php
Section::make(__('labels.invoice_information'))
    ->columnSpanFull()
    ->columns(2)
    ->schema([
        DatePicker::make('invoice_date')
            ->label(__('labels.invoice_date'))
            ->native(false)
            ->format('d-m-Y')
            ->default(now()->format('d-m-Y'))
            ->required(),
        DatePicker::make('sepa_transfer_date')
            ->label(__('labels.sepa_transfer_date'))
            ->native(false)
            ->format('d-m-Y')
            ->default(now()->addDays(14)->format('d-m-Y'))
            ->required(),
        Checkbox::make('attach_invoices')
            ->label(__('labels.attach_invoices'))
            ->columnSpanFull()
            ->visibleOn(Operation::Create)
            ->default(true),
    ])
```

### Filament create page

`app/Filament/Admin/Resources/InvoiceBatches/Pages/CreateInvoiceBatch.php` — pass the SEPA transfer date through:

```php
$batchId = $batchService->createBatch(
    invoiceDate: CarbonImmutable::parse($data['invoice_date']),
    sepaTransferDate: CarbonImmutable::parse($data['sepa_transfer_date']),
);
```

### Filament table

`app/Filament/Admin/Resources/InvoiceBatches/Tables/InvoiceBatchesTable.php` — surface the new date:

```php
TextColumn::make('invoice_date')
    ->label(__('labels.invoice_date'))
    ->date()
    ->sortable(),
TextColumn::make('sepa_transfer_date')
    ->label(__('labels.sepa_transfer_date'))
    ->date()
    ->sortable(),
```

### Language

`lang/nl/labels.php` — add a label near the existing `invoice_date` entry:

```php
'invoice_date' => 'Factuurdatum',
'sepa_transfer_date' => 'SEPA incassodatum',
```

## Tests

### Unit — `InvoiceBatchTest`

`tests/Unit/Domain/Invoices/InvoiceBatchTest.php`:

```php
public function test_it_stores_invoice_date(): void
{
    $invoiceDate = CarbonImmutable::parse('2026-05-25');
    $sepaTransferDate = CarbonImmutable::parse('2026-06-08');

    $subject = new InvoiceBatch(invoiceDate: $invoiceDate, sepaTransferDate: $sepaTransferDate);

    static::assertSame($invoiceDate, $subject->invoiceDate);
    static::assertSame($sepaTransferDate, $subject->sepaTransferDate);
}
```

### Unit — `InvoiceBatchGeneratorImplTest`

`tests/Unit/Domain/Invoices/InvoiceBatchGeneratorImplTest.php` — build `InvoiceBatch` with both dates and expect `createBatch` with both:

```php
$invoiceDate = new DateTimeImmutable('2026-05-25');
$sepaTransferDate = new DateTimeImmutable('2026-06-08');
$batch = new InvoiceBatch($invoiceDate, $sepaTransferDate);

$this->batchService->expectsCreateBatch($invoiceDate, $sepaTransferDate, $batchId);
```

### Unit — `InvoiceBatchServiceTest` / `InvoiceBatchServiceImplTest`

Update `test_create_batch` to pass and expect the SEPA transfer date:

```php
public function test_create_batch(): void
{
    $invoiceDate = CarbonImmutable::parse('2026-05-15');
    $sepaTransferDate = CarbonImmutable::parse('2026-05-29');
    $expectedId = InvoiceBatchId::create(1);

    $this->repo->expectsCreate($invoiceDate, $sepaTransferDate, InvoiceBatchStatus::Open, $expectedId);

    $result = $this->service->createBatch($invoiceDate, $sepaTransferDate);

    static::assertSame($expectedId, $result);
}
```

### Unit — `InvoiceBatchServiceExpectation`

`tests/Unit/Domain/Invoices/InvoiceBatchServiceExpectation.php`:

```php
public function expectsCreateBatch(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate, InvoiceBatchId $return): void
{
    $this->mock
        ->expects('createBatch')
        ->with(equalTo($invoiceDate), equalTo($sepaTransferDate))
        ->andReturn($return);
}
```

### Unit — `InvoiceBatchRepositoryExpectation`

`tests/Unit/Domain/Invoices/InvoiceBatchRepositoryExpectation.php`:

```php
public function expectsCreate(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate, InvoiceBatchStatus $status, InvoiceBatchId $return): void
{
    $this->mock
        ->expects('create')
        ->with(equalTo($invoiceDate), equalTo($sepaTransferDate), equalTo($status))
        ->andReturn($return);
}
```

### Feature — `InvoiceBatchRepositoryDbTest`

`tests/Feature/Infrastructure/Invoices/InvoiceBatchRepositoryDbTest.php`:

Update `test_create_batch`:

```php
public function test_create_batch(): void
{
    $date = CarbonImmutable::parse('2026-05-15');
    $sepaDate = CarbonImmutable::parse('2026-05-29');

    $batchId = $this->repository->create($date, $sepaDate, InvoiceBatchStatus::Open);

    $this->assertDatabaseHas('invoice_batches', [
        'id' => $batchId->value,
        'status' => InvoiceBatchStatus::Open->value,
        'sepa_transfer_date' => '2026-05-29',
    ]);
}
```

Rename the `getBatchDate` tests and add a date-override test:

```php
public function test_get_sepa_transfer_date_returns_sepa_transfer_date(): void
{
    $date = CarbonImmutable::parse('2026-06-15');
    $sepaDate = CarbonImmutable::parse('2026-06-29');
    $batch = InvoiceBatch::factory()->create([
        'invoice_date' => $date,
        'sepa_transfer_date' => $sepaDate,
    ]);

    $result = $this->repository->getSepaTransferDate(InvoiceBatchId::create($batch->id));

    static::assertEquals($sepaDate->toDateString(), $result->format('Y-m-d'));
}

public function test_mark_invoices_as_pending_overrides_invoice_date(): void
{
    $batch = InvoiceBatch::factory()->create(['invoice_date' => '2026-06-15']);

    $invoice = Invoice::factory()
        ->forBatch($batch)
        ->createQuietly([
            'status' => InvoiceStatus::Open,
            'date' => '2026-05-01',
        ]);

    $this->repository->markInvoicesAsPending(InvoiceBatchId::create($batch->id));

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'status' => InvoiceStatus::Pending->value,
        'date' => '2026-06-15 00:00:00',
    ]);
}
```

### Feature — `InvoiceBatchRepositoryExpectation`

`tests/Feature/Infrastructure/Invoices/InvoiceBatchRepositoryExpectation.php` — rename `expectsGetBatchDate` to `expectsGetSepaTransferDate`:

```php
public function expectsGetSepaTransferDate(InvoiceBatchId $batchId, DateTimeInterface $return): void
{
    $this->mock
        ->expects('getSepaTransferDate')
        ->with(equalTo($batchId))
        ->andReturn($return);
}
```

### Feature — `SepaExportServiceImplTest`

`tests/Feature/Infrastructure/Invoices/SepaExportServiceImplTest.php` — replace every `$this->repo->expectsGetBatchDate(...)` call with `$this->repo->expectsGetSepaTransferDate(...)`. The value can stay the same (the test is asserting that whatever the repository returns becomes the `ReqdColltnDt`).

### Feature — `InvoiceMailRepositoryDbTest`

`tests/Feature/Infrastructure/Invoices/InvoiceMailRepositoryDbTest.php` — update the batch fixtures and assertions to use `sepa_transfer_date` instead of relying on `invoice_date` for the collection date:

```php
$batch = InvoiceBatch::factory()->createQuietly([
    'invoice_date' => '2026-06-30',
    'sepa_transfer_date' => '2026-07-15',
]);

// ...

static::assertNotNull($result->sepaTransferDate);
static::assertSame('2026-07-15', $result->sepaTransferDate->format('Y-m-d'));
```

Update the two "null when no …" cases to set `sepa_transfer_date` on the batch fixture too (the assertion remains `assertNull`).

## Verification

```bash
./Taskfile artisan test --compact tests/Unit/Domain/Invoices/InvoiceBatchTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Invoices/InvoiceBatchGeneratorImplTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Invoices/InvoiceBatchServiceTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Invoices/InvoiceBatchServiceImplTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Invoices/InvoiceBatchRepositoryDbTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Invoices/SepaExportServiceImplTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Invoices/InvoiceMailRepositoryDbTest.php
```
