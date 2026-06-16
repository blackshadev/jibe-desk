# Invoice Batch Management & SEPA Direct Debit Export

## Overview

Implement a complete invoice batch management system with SEPA Direct Debit XML export capabilities. Users can create batches (optionally auto-attaching open invoices from last month), add/remove invoices directly, close batches (marking invoices as pending), export SEPA XML files, mark batches as completed, and manage invoice statuses (Paid/Declined) from within the batch view.

## Architecture

The implementation follows the existing DDD architecture:
- **Domain layer** (`app/Domain/Invoices/`): Business logic services, repository interfaces, value objects, DTOs
- **Infrastructure layer** (`app/Infrastructure/Invoices/`): Database repositories, SEPA XML generation
- **Filament layer** (`app/Filament/Admin/Resources/InvoiceBatches/`): Admin UI

---

## Step 1: Install `digitick/sepa-xml` Package

```bash
./Taskfile composer require digitick/sepa-xml
```

---

## Step 2: Add SEPA Configuration

**Create** `config/sepa.php`

```php
<?php

declare(strict_types=1);

return [
    'creditor_id' => env('SEPA_CREDITOR_ID'),
    'creditor_name' => env('SEPA_CREDITOR_NAME', 'WSV Almere Centraal'),
    'creditor_iban' => env('SEPA_CREDITOR_IBAN'),
    'creditor_bic' => env('SEPA_CREDITOR_BIC'),
    'pain_format' => env('SEPA_PAIN_FORMAT', 'pain.008.001.02'),
];
```

**Add** corresponding environment variables to `.env.example`:
```
SEPA_CREDITOR_ID=
SEPA_CREDITOR_NAME="WSV Almere Centraal"
SEPA_CREDITOR_IBAN=
SEPA_CREDITOR_BIC=
SEPA_PAIN_FORMAT=pain.008.001.02
```

---

## Step 3: Database Migration

### 3a: Add `status` to `invoice_batches`

**Create** migration `add_status_to_invoice_batches`

```php
Schema::table('invoice_batches', function (Blueprint $table) {
    $table->string('status')->index()->default('open');
});
```

### 3b: Add `declined` to `InvoiceStatus` enum

**Modify** `app/Domain/Invoices/InvoiceStatus.php`

```php
enum InvoiceStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Paid = 'paid';
    case Declined = 'declined';
}
```

### 3c: Add `InvoiceBatchStatus` enum

**Create** `app/Domain/Invoices/InvoiceBatchStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

enum InvoiceBatchStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Completed = 'completed';
}
```

---

## Step 4: Update Eloquent Models

### 4a: Update `InvoiceBatch` model

**Modify** `app/Models/InvoiceBatch.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchStatus;
use App\Domain\Invoices\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

#[Fillable(['invoice_date', 'status'])]
final class InvoiceBatch extends Model
{
    use HasFactory;

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'status' => InvoiceBatchStatus::class,
        ];
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function total(): Attribute
    {
        return Attribute::get(
            fn () => $this->invoices->reduce(
                static fn (CompoundPrice $total, Invoice $invoice): CompoundPrice => $total->add($invoice->total),
                CompoundPrice::empty(),
            ),
        );
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function openTotal(): Attribute
    {
        return Attribute::get(
            fn () => $this->invoices
                ->filter(static fn (Invoice $invoice) => $invoice->status === InvoiceStatus::Open)
                ->reduce(
                    static fn (CompoundPrice $total, Invoice $invoice): CompoundPrice => $total->add($invoice->total),
                    CompoundPrice::empty(),
                ),
        );
    }

    /** @return Attribute<int, never> */
    protected function invoiceCount(): Attribute
    {
        return Attribute::get(fn () => $this->invoices()->count());
    }
}
```

### 4b: Update `Invoice` model

**Add** relationship to `InvoiceBatch` in `app/Models/Invoice.php`:

```php
/** @return BelongsTo<InvoiceBatch, $this> */
public function invoiceBatch(): BelongsTo
{
    return $this->belongsTo(InvoiceBatch::class);
}
```

### 4c: Update `InvoiceBatchFactory`

**Add** `pending()` and `completed()` states:

```php
public function pending(): self
{
    // @mago-expect lint:prefer-static-closure
    return $this->state(fn () => ['status' => InvoiceBatchStatus::Pending->value]);
}

public function completed(): self
{
    // @mago-expect lint:prefer-static-closure
    return $this->state(fn () => ['status' => InvoiceBatchStatus::Completed->value]);
}
```

### 4d: Update `InvoiceFactory`

**Add** a `forBatch(InvoiceBatch $batch)` state:

```php
public function forBatch(InvoiceBatch $batch): self
{
    // @mago-expect lint:prefer-static-closure
    return $this->state(fn () => ['invoice_batch_id' => $batch->id]);
}
```

---

## Step 5: Domain Layer

### 5a: `SepaExportInvoice` DTO

**Create** `app/Domain/Invoices/SepaExportInvoice.php`

A readonly DTO representing a single invoice's data needed for SEPA export:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use DateTimeInterface;

final readonly class SepaExportInvoice
{
    public function __construct(
        public InvoiceId $invoiceId,
        public string $invoiceNumber,
        public string $recipientName,
        public CompoundPrice $total,
        public string $iban,
        public string $bic,
        public string $mandateId,
        public DateTimeInterface $mandateDate,
    ) {}

    /** Amount in cents (price + vat) for SEPA */
    public function amountInCents(): int
    {
        return (int) round($this->total->price * 100);
    }
}
```

### 5b: `InvoiceBatchRepository` interface

**Create** `app/Domain/Invoices/InvoiceBatchRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface InvoiceBatchRepository
{
    public function create(DateTimeInterface $invoiceDate, InvoiceBatchStatus $status): InvoiceBatchId;

    public function addOpenInvoicesFromPreviousMonth(InvoiceBatchId $batchId): void;

    /** @return list<SepaExportInvoice> */
    public function getInvoicesForExport(InvoiceBatchId $batchId): array;

    public function markInvoicesAsPending(InvoiceBatchId $batchId): void;

    public function updateInvoiceStatus(InvoiceId $invoiceId, InvoiceStatus $status): void;

    public function closeBatch(InvoiceBatchId $batchId): void;

    public function completeBatch(InvoiceBatchId $batchId): void;
}
```

### 5c: `InvoiceBatchService` domain service

**Create** `app/Domain/Invoices/InvoiceBatchService.php`

Minimal service — only orchestrates domain-level batch operations. Individual invoice attachment/removal is handled directly by Filament via Eloquent (no service bloat).

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use DateTimeInterface;

final readonly class InvoiceBatchService
{
    public function __construct(
        private InvoiceBatchRepository $batchRepository,
    ) {}

    public function createBatch(DateTimeInterface $invoiceDate): InvoiceBatchId
    {
        return $this->batchRepository->create($invoiceDate, InvoiceBatchStatus::Open);
    }
    
    public function attachOpenInvoices(InvoiceBatchId $batchId): void
    {
        $this->batchRepository->addOpenInvoicesFromBatchMonth($batchId);
    }

    public function closeBatch(InvoiceBatchId $batchId): void
    {
        $this->batchRepository->markInvoicesAsPending($batchId);
        $this->batchRepository->closeBatch($batchId);
    }

    /**
     * @throws \DomainException If batch still has open or pending invoices
     */
    public function completeBatch(InvoiceBatchId $batchId): void
    {
        $this->batchRepository->completeBatch($batchId);
    }

    public function updateInvoiceStatus(InvoiceId $invoiceId, InvoiceStatus $status): void
    {
        $this->batchRepository->updateInvoiceStatus($invoiceId, $status);
    }
}
```

### 5d: `SepaExportService` interface

**Create** `app/Domain/Invoices/SepaExportService.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface SepaExportService
{
    /**
     * Generate a SEPA Direct Debit XML file for the given batch.
     * Returns the XML content as a string.
     */
    public function export(InvoiceBatch $batch): string;
}
```

---

## Step 6: Infrastructure Layer

### 6a: `InvoiceBatchRepositoryDb`

**Create** `app/Infrastructure/Invoices/InvoiceBatchRepositoryDb.php`

Implements `InvoiceBatchRepository`. Key method implementations:

#### `create()`
Creates an `InvoiceBatch` model with the given status, returns `InvoiceBatchId`.

#### `addOpenInvoicesFromPreviousMonth()`
```php
public function addOpenInvoicesFromPreviousMonth(InvoiceBatchId $batchId): void
{
    $month = new CarbonImmutable(InvoiceBatch::find($batchId->value)->invoice_date);

    Invoice::query()
        ->whereNull('invoice_batch_id')
        ->where('status', InvoiceStatus::Open)
        ->whereBetween('date', [
            $month->subMonth()->startOfMonth(),
            $month->endOfMonth(),
        ])
        ->update(['invoice_batch_id' => $batchId->value]);
}
```

#### `getInvoicesForExport()`
Returns `list<SepaExportInvoice>`. Queries invoices in the batch with their member's payment information, computing line totals via a database query (not the computed attribute) for performance:

```php
/** @return list<SepaExportInvoice> */
public function getInvoicesForExport(InvoiceBatchId $batchId): array
{
    $invoices = Invoice::query()
        ->where('invoice_batch_id', $batchId->value)
        ->join('invoice_lines', 'invoices.id', '=', 'invoice_lines.invoice_id')
        ->join('payment_information', 'invoices.member_id', '=', 'payment_information.member_id')
        ->select(
            'invoices.id as invoice_id',
            'invoices.invoice_number',
            'invoices.recipient_name',
            'payment_information.iban',
            'payment_information.bic',
            'payment_information.uuid as mandate_id',
            'payment_information.mandate_accepted_date as mandate_date',
            DB::raw('SUM(invoice_lines.price * invoice_lines.quantity) as total_price'),
            DB::raw('SUM(invoice_lines.vat * invoice_lines.quantity) as total_vat'),
        )
        ->groupBy(
            'invoices.id',
            'invoices.invoice_number',
            'invoices.recipient_name',
            'payment_information.iban',
            'payment_information.bic',
            'payment_information.uuid',
            'payment_information.mandate_accepted_date',
        )
        ->get();

    return $invoices->map(static fn (object $row) => new SepaExportInvoice(
        invoiceId: new InvoiceId((int) $row->invoice_id),
        invoiceNumber: $row->invoice_number,
        recipientName: $row->recipient_name,
        total: new CompoundPrice((float) $row->total_price, (float) $row->total_vat),
        iban: $row->iban,
        bic: $row->bic,
        mandateId: $row->mandate_id,
        mandateDate: CarbonImmutable::parse($row->mandate_date),
    ))->all();
}
```

#### `markInvoicesAsPending()`
Updates all invoices in the batch with status `Open` to `Pending`.

#### `updateInvoiceStatus()`
Updates a single invoice's status by `InvoiceId`.

#### `closeBatch()`
Updates batch status to `Pending`.

#### `completeBatch()`
Checks that all invoices in the batch are `Paid` or `Declined`. If any are `Open` or `Pending`, throws a `\DomainException`. Otherwise sets batch status to `Completed`.

```php
public function completeBatch(InvoiceBatchId $batchId): void
{
    $batch = InvoiceBatch::findOrFail($batchId->value);

    $nonCompletableCount = $batch->invoices()
        ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Pending])
        ->count();

    if ($nonCompletableCount > 0) {
        throw new \DomainException('Batch still has open or pending invoices.');
    }

    $batch->update(['status' => InvoiceBatchStatus::Completed]);
}
```

### 6b: `SepaExportServiceImpl`

**Create** `app/Infrastructure/Invoices/SepaExportServiceImpl.php`

Implements `SepaExportService`. Uses the `SepaExportInvoice` DTO:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices;

use App\Domain\Invoices\InvoiceBatch;
use App\Domain\Invoices\InvoiceBatchRepository;
use App\Domain\Invoices\SepaExportInvoice;
use App\Domain\Invoices\SepaExportService;
use Digitick\Sepa\PaymentInformation;
use Digitick\Sepa\TransferFile\Factory\TransferFileFacadeFactory;
use Digitick\Sepa\TransferInformation\CustomerDirectDebitTransferInformation;
use Override;

final readonly class SepaExportServiceImpl implements SepaExportService
{
    public function __construct(
        private InvoiceBatchRepository $batchRepository,
    ) {}

    #[Override]
    public function export(InvoiceBatch $batch): string
    {
        /** @var list<SepaExportInvoice> $invoices */
        $invoices = $this->batchRepository->getInvoicesForExport($batch->id);

        $paymentInfoId = 'payment-batch-' . $batch->id->value;

        $directDebit = TransferFileFacadeFactory::createDirectDebit(
            messageIdentification: 'BATCH-' . $batch->id->value,
            initiatingPartyName: config('sepa.creditor_name'),
            painFormat: config('sepa.pain_format'),
        );

        $directDebit->addPaymentInfo($paymentInfoId, [
            'id'                  => $paymentInfoId,
            'creditorName'        => config('sepa.creditor_name'),
            'creditorAccountIBAN' => config('sepa.creditor_iban'),
            'creditorAgentBIC'    => config('sepa.creditor_bic'),
            'seqType'             => PaymentInformation::S_ONEOFF,
            'creditorId'          => config('sepa.creditor_id'),
        ]);

        foreach ($invoices as $invoice) {
            $transfer = new CustomerDirectDebitTransferInformation(
                $invoice->amountInCents(),
                $invoice->iban,
                $invoice->recipientName,
                'INV-' . $invoice->invoiceNumber,
            );

            $transfer->setBic($invoice->bic);
            $transfer->setMandateId($invoice->mandateId);
            $transfer->setMandateSignDate($invoice->mandateDate);
            $transfer->setRemittanceInformation($invoice->invoiceNumber);

            $directDebit->addTransfer($paymentInfoId, $transfer);
        }

        return $directDebit->asXML();
    }
}
```

---

## Step 7: Filament Resource

### 7a: Resource class

**Create** `app/Filament/Admin/Resources/InvoiceBatches/InvoiceBatchResource.php`

```php
final class InvoiceBatchResource extends Resource
{
    protected static ?string $model = InvoiceBatch::class;
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Invoicing;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentDuplicate;
    protected static ?string $recordTitleAttribute = 'id';

    public static function table(Table $table): Table { return InvoiceBatchesTable::configure($table); }
    public static function getPluralLabel(): string { return __('labels.invoice_batches'); }
    public static function getLabel(): string { return __('labels.invoice_batch'); }
    public static function getPages(): array {
        return [
            'index' => ListInvoiceBatches::route('/'),
            'create' => CreateInvoiceBatch::route('/create'),
            'view' => ViewInvoiceBatch::route('/{record}'),
        ];
    }
}
```

### 7b: Table class

**Create** `app/Filament/Admin/Resources/InvoiceBatches/Tables/InvoiceBatchesTable.php`

Columns:
- `id` — `TextColumn::make('id')->label(__('labels.id'))`
- `invoice_date` — `TextColumn::make('invoice_date')->label(__('labels.invoice_date'))->date()->sortable()`
- `status` — `TextColumn::make('status')->label(__('labels.status'))->formatStateUsing(fn (InvoiceBatchStatus $state) => __('labels.batch_status.' . $state->value))`
- `invoice_count` — `TextColumn::make('invoice_count')->label(__('labels.invoice_count'))->sortable()`
- `open_total` — `TextColumn::make('open_total')->label(__('labels.open_total'))->formatStateUsing(fn (CompoundPrice $state) => (string) $state)->alignEnd()`
- `total` — `TextColumn::make('total')->label(__('labels.total'))->formatStateUsing(fn (CompoundPrice $state) => (string) $state)->alignEnd()`
- `created_at` — toggleable, hidden by default

Record URL: Use a custom closure that always returns the `view` page URL:
```php
->recordUrl(static fn (InvoiceBatch $record) => InvoiceBatchResource::getUrl('view', ['record' => $record]))
```

### 7c: Create page

**Create** `app/Filament/Admin/Resources/InvoiceBatches/Pages/CreateInvoiceBatch.php`

Form fields:
- `invoice_date` — DatePicker, native(false), format 'd-m-Y'
- `attach_previous_month` — Checkbox, label "Facturen van vorige maand automatisch toevoegen", default false

```php
final class CreateInvoiceBatch extends CreateRecord
{
    protected static string $resource = InvoiceBatchResource::class;

    private readonly InvoiceBatchService $batchService;

    public function __construct()
    {
        $this->batchService = app(InvoiceBatchService::class);
    }

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $attachPreviousMonth = $data['attach_previous_month'] ?? false;
        unset($data['attach_previous_month']);

        $batchId = $this->batchService->createBatch(
            invoiceDate: CarbonImmutable::parse($data['invoice_date']),
            attachPreviousMonth: $attachPreviousMonth,
        );

        return InvoiceBatch::findOrFail($batchId->value);
    }

    #[Override]
    protected function getCreatedNotificationTitle(): string
    {
        return __('notifications.batch_created');
    }
}
```

### 7d: View page (main interaction page)

**Create** `app/Filament/Admin/Resources/InvoiceBatches/Pages/ViewInvoiceBatch.php`

This is the primary page for managing a batch. It contains:

#### Header Actions:
1. **Add Invoices** — `Action::make('addInvoices')`
   - Shows a `Select::make('invoice_ids')` with `multiple()` that lists all open invoices without a batch
   - Query: `Invoice::whereNull('invoice_batch_id')->where('status', InvoiceStatus::Open)->orderBy('invoice_number')`
   - Option label: `{invoice_number} — {recipient_name}`
   - On success: directly updates `invoice_batch_id` on selected Invoice models via Eloquent (no service call needed)
   - Hidden when batch status is not `Open`

2. **Close Batch** — `Action::make('closeBatch')`
   - `->requiresConfirmation()`
   - Calls `InvoiceBatchService::closeBatch()`
   - Hidden when batch status is not `Open`

3. **Export SEPA** — `Action::make('exportSepa')`
   - Calls `SepaExportService::export()`
   - Returns a `StreamedResponse` download of the XML file
   - Filename: `sepa-batch-{id}-{date}.xml`
   - Visible when batch status is `Pending` (can always re-export pending batches)

4. **Complete Batch** — `Action::make('completeBatch')`
   - `->requiresConfirmation()`
   - Calls `InvoiceBatchService::completeBatch()`
   - Hidden when batch status is not `Pending`
   - If there are still open/pending invoices, the service will throw a DomainException — catch it and show an error notification

#### Infolist:
- Display batch metadata: `id`, `invoice_date`, `status`, `invoice_count`, `open_total`, `total`

#### Relation Manager:
- `InvoiceBatchInvoicesRelationManager` — shows all invoices in the batch (see 7e)

### 7e: InvoiceBatchInvoices Relation Manager

**Create** `app/Filament/Admin/Resources/InvoiceBatches/RelationManagers/InvoiceBatchInvoicesRelationManager.php`

```php
final class InvoiceBatchInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';
    protected static ?string $relatedResource = InvoiceResource::class;
}
```

#### Table Columns:
- `invoice_number` — `TextColumn`, searchable, link to invoice view
- `recipient_name` — `TextColumn`, label `__('labels.recipient_name')`
- `total` — `TextColumn`, format using `CompoundPrice`, alignEnd
- `status` — `TextColumn`, format using `InvoiceStatusLabels`

#### Record Actions (row-level):
1. **Mark as Paid** — `Action::make('markAsPaid')`
   - `->icon(Heroicon::Banknotes)`
   - `->requiresConfirmation()`
   - `->hidden(static fn (Invoice $record) => $record->status !== InvoiceStatus::Open && $record->status !== InvoiceStatus::Pending)`
   - Directly updates `status` to `Paid` on the Invoice model via Eloquent

2. **Mark as Declined** — `Action::make('markAsDeclined')`
   - `->icon(Heroicon::XCircle)`
   - `->color('danger')`
   - `->requiresConfirmation()`
   - `->hidden(static fn (Invoice $record) => $record->status !== InvoiceStatus::Open && $record->status !== InvoiceStatus::Pending)`
   - Directly updates `status` to `Declined` on the Invoice model via Eloquent

3. **Remove from Batch** — `Action::make('removeFromBatch')`
   - `->icon(Heroicon::MinusCircle)`
   - `->color('danger')`
   - `->requiresConfirmation()`
   - `->hidden(static fn (Invoice $record, InvoiceBatch $ownerRecord) => $ownerRecord->status !== InvoiceBatchStatus::Open)`
   - Sets `invoice_batch_id` to `null` on the Invoice model via Eloquent

#### Record URL:
- `ViewOrEdit::route(InvoiceResource::class)` — click through to the invoice

---

## Step 8: Labels & Translations

**Add** to `lang/nl/labels.php`:

```php
'invoice_batch' => 'Factuurbatch',
'invoice_batches' => 'Factuurbatchen',
'invoice_count' => 'Aantal facturen',
'open_total' => 'Openstaand bedrag',
'add_invoices' => 'Facturen toevoegen',
'close_batch' => 'Batch sluiten',
'complete_batch' => 'Batch afronden',
'export_sepa' => 'SEPA export',
'mark_as_paid' => 'Markeer als betaald',
'mark_as_declined' => 'Markeer als geweigerd',
'remove_from_batch' => 'Verwijder uit batch',
'invoice_status' => [
    'open' => 'Open',
    'pending' => 'In behandeling',
    'paid' => 'Betaald',
    'declined' => 'Geweigerd',
],
'batch_status' => [
    'open' => 'Open',
    'pending' => 'In behandeling',
    'completed' => 'Afgerond',
],
```

**Add** to `lang/nl/notifications.php`:

```php
'batch_created' => 'Factuurbatch succesvol aangemaakt',
'invoices_added_to_batch' => 'Facturen succesvol toegevoegd aan batch',
'invoice_removed_from_batch' => 'Factuur succesvol verwijderd uit batch',
'batch_closed' => 'Factuurbatch succesvol gesloten',
'batch_completed' => 'Factuurbatch succesvol afgerond',
'batch_not_completable' => 'De batch kan niet worden afgerond: er zijn nog open of in behandeling zijnde facturen',
'invoice_status_updated' => 'Factuurstatus succesvol bijgewerkt',
'sepa_exported' => 'SEPA-bestand succesvol geëxporteerd',
'no_invoices_to_export' => 'Geen facturen om te exporteren',
```

**Add** `InvoiceBatchStatusLabels` class:

**Create** `app/Filament/Admin/Labels/InvoiceBatchStatusLabels.php`

```php
final class InvoiceBatchStatusLabels
{
    public static function options(): array
    {
        return [
            InvoiceBatchStatus::Open->value => __('labels.batch_status.open'),
            InvoiceBatchStatus::Pending->value => __('labels.batch_status.pending'),
            InvoiceBatchStatus::Completed->value => __('labels.batch_status.completed'),
        ];
    }
}
```

**Update** `InvoiceStatusLabels` to include `Declined`:

```php
InvoiceStatus::Declined->value => __('labels.invoice_status.declined'),
```

---

## Step 9: Policy

**Create** `app/Policies/InvoiceBatchPolicy.php`

```php
final class InvoiceBatchPolicy
{
    public function viewAny(User $_user): bool { return true; }
    public function view(User $_user, InvoiceBatch $_batch): bool { return true; }
    public function create(User $_user): bool { return true; }
    public function update(User $_user, InvoiceBatch $_batch): bool { return true; }
    public function delete(User $_user, InvoiceBatch $_batch): bool { return true; }
}
```

---

## Step 10: Tests

### 10a: Unit Tests

**Create** `tests/Unit/Domain/Invoices/InvoiceBatchServiceTest.php`

Test cases:
- `testCreateBatch()` — verifies repository `create()` is called with correct date and `Open` status
- `testCreateBatchWithPreviousMonth()` — verifies `create()` then `addOpenInvoicesFromPreviousMonth()` are called
- `testCreateBatchWithoutPreviousMonth()` — verifies `addOpenInvoicesFromPreviousMonth()` is NOT called
- `testCloseBatch()` — verifies `markInvoicesAsPending()` then `closeBatch()` are called in order
- `testCompleteBatch()` — verifies repository `completeBatch()` is called
- `testUpdateInvoiceStatus()` — verifies repository `updateInvoiceStatus()` is called

Uses Mockery expectation classes for `InvoiceBatchRepository`.

**Create** `tests/Unit/Domain/Expectations/InvoiceBatchRepositoryExpectation.php`

Mockery expectation class following the existing pattern (see `InvoiceRepositoryExpectation`).

### 10b: Feature Tests

**Create** `tests/Feature/Infrastructure/Invoices/InvoiceBatchRepositoryDbTest.php`

Test cases:
- `testCreateBatch()` — creates batch, asserts DB has record with correct status
- `testAddOpenInvoicesFromPreviousMonth()` — creates batch-less open invoices from last month and this month, calls method, asserts only last month's invoices got the batch ID
- `testAddOpenInvoicesSkipsNonOpenInvoices()` — creates paid/declined invoices from last month, asserts they are not attached
- `testAddOpenInvoicesSkipsAlreadyBatchedInvoices()` — creates invoices already in a batch, asserts they are not re-attached
- `testCloseBatch()` — closes batch, asserts batch status is `pending` and invoices are `pending`
- `testCompleteBatch()` — creates batch with all paid/declined invoices, completes, asserts status is `completed`
- `testCompleteBatchFailsWithOpenInvoices()` — creates batch with open invoices, asserts DomainException
- `testCompleteBatchFailsWithPendingInvoices()` — creates batch with pending invoices, asserts DomainException
- `testGetInvoicesForExport()` — creates invoices with payment info and lines, asserts correct DTO structure
- `testGetInvoicesForExportHandlesMissingPaymentInfo()` — tests edge case
- `testUpdateInvoiceStatus()` — updates status, asserts DB has new status

**Create** `tests/Feature/Infrastructure/Invoices/SepaExportServiceImplTest.php`

Test cases:
- `testExportGeneratesValidXml()` — creates batch with invoices and payment info, exports, asserts XML is valid and contains expected elements (creditor info, debtor info, amounts)
- `testExportWithNoInvoices()` — asserts the export still produces valid XML (or throws a meaningful exception)

**Create** `tests/Feature/Filament/InvoiceBatches/InvoiceBatchResourceTest.php`

Test cases:
- `testCanListBatches()` — asserts table renders with correct columns including `open_total`
- `testCanCreateBatch()` — creates batch via form without auto-attach, asserts DB record
- `testCanCreateBatchWithPreviousMonthAutoAttach()` — creates invoices from last month, creates batch with checkbox checked, asserts invoices are attached
- `testCanViewBatch()` — views batch, asserts infolist and relation manager render
- `testCanAddInvoicesToBatch()` — calls addInvoices action, asserts invoices are attached
- `testCanCloseBatch()` — calls closeBatch action, asserts status changes
- `testCanExportSepa()` — calls exportSepa action, asserts download response
- `testCanCompleteBatch()` — with all paid/declined invoices, calls completeBatch, asserts completed
- `testCannotCompleteBatchWithOpenInvoices()` — asserts error notification
- `testCanMarkInvoiceAsPaid()` — calls markAsPaid on invoice row, asserts status change
- `testCanMarkInvoiceAsDeclined()` — calls markAsDeclined on invoice row, asserts status change
- `testCanRemoveInvoiceFromBatch()` — calls removeFromBatch, asserts invoice_batch_id is null
- `testCannotAddInvoicesToClosedBatch()` — asserts addInvoices action is hidden
- `testCanReExportPendingBatch()` — asserts export action is visible on pending batch

---

## File Summary

### New Files:
| File | Purpose |
|------|---------|
| `config/sepa.php` | SEPA creditor configuration |
| `app/Domain/Invoices/InvoiceBatchStatus.php` | Batch status enum (Open, Pending, Completed) |
| `app/Domain/Invoices/InvoiceBatchRepository.php` | Repository interface |
| `app/Domain/Invoices/InvoiceBatchService.php` | Domain service |
| `app/Domain/Invoices/SepaExportInvoice.php` | DTO for SEPA export invoice data |
| `app/Domain/Invoices/SepaExportService.php` | Export service interface |
| `app/Infrastructure/Invoices/InvoiceBatchRepositoryDb.php` | Repository implementation |
| `app/Infrastructure/Invoices/SepaExportServiceImpl.php` | SEPA export implementation |
| `app/Filament/Admin/Resources/InvoiceBatches/InvoiceBatchResource.php` | Filament resource |
| `app/Filament/Admin/Resources/InvoiceBatches/Tables/InvoiceBatchesTable.php` | Table config |
| `app/Filament/Admin/Resources/InvoiceBatches/Schemas/InvoiceBatchForm.php` | Create form schema |
| `app/Filament/Admin/Resources/InvoiceBatches/Pages/CreateInvoiceBatch.php` | Create page |
| `app/Filament/Admin/Resources/InvoiceBatches/Pages/ListInvoiceBatches.php` | List page |
| `app/Filament/Admin/Resources/InvoiceBatches/Pages/ViewInvoiceBatch.php` | View page with actions |
| `app/Filament/Admin/Resources/InvoiceBatches/RelationManagers/InvoiceBatchInvoicesRelationManager.php` | Invoices relation manager |
| `app/Filament/Admin/Labels/InvoiceBatchStatusLabels.php` | Status label mapper |
| `app/Policies/InvoiceBatchPolicy.php` | Authorization policy |
| `tests/Unit/Domain/Invoices/InvoiceBatchServiceTest.php` | Unit tests for service |
| `tests/Unit/Domain/Expectations/InvoiceBatchRepositoryExpectation.php` | Mockery expectation |
| `tests/Feature/Infrastructure/Invoices/InvoiceBatchRepositoryDbTest.php` | Repository feature tests |
| `tests/Feature/Infrastructure/Invoices/SepaExportServiceImplTest.php` | Export feature tests |
| `tests/Feature/Filament/InvoiceBatches/InvoiceBatchResourceTest.php` | Filament resource tests |

### Modified Files:
| File | Change |
|------|--------|
| `composer.json` | Add `digitick/sepa-xml` dependency |
| `app/Domain/Invoices/InvoiceStatus.php` | Add `Declined` case |
| `app/Models/InvoiceBatch.php` | Add relationships, casts, computed attributes (`total`, `openTotal`, `invoiceCount`) |
| `app/Models/Invoice.php` | Add `invoiceBatch()` relationship |
| `database/factories/InvoiceBatchFactory.php` | Add `pending()` and `completed()` states |
| `database/factories/InvoiceFactory.php` | Add `forBatch()` state |
| `app/Filament/Admin/Labels/InvoiceStatusLabels.php` | Add `Declined` label |
| `lang/nl/labels.php` | Add batch-related labels |
| `lang/nl/notifications.php` | Add batch-related notifications |

---

## Implementation Order

1. Install `digitick/sepa-xml` package
2. Create `config/sepa.php` and update `.env.example`
3. Create migration for `status` on `invoice_batches`
4. Add `InvoiceBatchStatus` enum and update `InvoiceStatus` enum
5. Update `InvoiceBatch` model (relationships, casts, computed attributes)
6. Update `Invoice` model (add `invoiceBatch()` relationship)
7. Update factories (`InvoiceBatchFactory`, `InvoiceFactory`)
8. Create `SepaExportInvoice` DTO
9. Create `InvoiceBatchRepository` interface
10. Create `InvoiceBatchService` domain service
11. Create `SepaExportService` interface
12. Create `InvoiceBatchRepositoryDb` implementation
13. Create `SepaExportServiceImpl` implementation
14. Create `InvoiceBatchPolicy`
15. Create `InvoiceBatchStatusLabels` and update `InvoiceStatusLabels`
16. Add translations (`labels.php`, `notifications.php`)
17. Create Filament resource (`InvoiceBatchResource`, table, form, pages, relation manager)
18. Write unit tests for `InvoiceBatchService`
19. Write feature tests for `InvoiceBatchRepositoryDb`
20. Write feature tests for `SepaExportServiceImpl`
21. Write feature tests for `InvoiceBatchResource`

---

## Key Decisions & Tradeoffs

1. **No edit page for batches**: Batches are created empty (or with auto-attached invoices), batch details are immutable after creation. Status changes happen through actions.

2. **Minimal service layer**: Individual invoice attachment/removal and status updates happen directly via Eloquent in the Filament actions. The service only handles domain-orchestrated operations: `createBatch` (with optional auto-attach), `closeBatch`, `completeBatch`, and `updateInvoiceStatus`.

3. **Auto-attach during creation**: When the "attach previous month" checkbox is checked, a single bulk `UPDATE` query attaches all open batch-less invoices from the previous calendar month. No per-invoice iteration needed.

4. **Batch statuses**: `Open` → `Pending` (after closing) → `Completed` (when all invoices are paid/declined). A batch can also go directly from `Open` to `Completed` if somehow all invoices are resolved before closing, but the normal flow is Open → Pending → Completed.

5. **SEPA export on Pending batches**: The export action is visible when batch status is `Pending`. Pending batches can always be re-exported (the user may need to re-submit after a bank rejection).

6. **`SepaExportInvoice` DTO**: Strongly typed, provides `amountInCents()` method. The repository builds these DTOs from a performant aggregate query (joins invoice_lines and payment_information, computes totals via `SUM`).

7. **`openTotal` on listing**: Shows the total amount of invoices still in `Open` status within each batch. Computed as a Laravel `Attribute` that filters the invoices collection.

8. **Complete batch validation**: The `completeBatch()` repository method checks for open/pending invoices and throws a `DomainException` if any exist. The Filament action catches this and shows an error notification.

9. **Record URL for batches**: Uses a custom closure pointing to the `view` page (no edit page exists).

10. **Removing invoices**: Only allowed when batch is `Open`. Once closed/pending, invoices cannot be removed.
