# Rename `InvoiceBatch` → `SepaBatch`

**Date:** 2026-10-17

## Overview

Rename the `InvoiceBatch` concept across the entire codebase to `SepaBatch`. The batch is no longer only about invoices: it will also carry purchase-order creditor payments in the SEPA export (see the companion plan `20261017_purchase-orders-in-sepa-batch-export.md`). This is a pure, behaviour-preserving rename — no business logic changes. It is the prerequisite for the purchase-order feature and must land first.

## Current situation

The batch entity is named after invoices even though it is really a **SEPA payment batch**:

- Eloquent model `App\Models\InvoiceBatch` backed by table `invoice_batches`.
- Column `invoices.invoice_batch_id` (nullable FK → `invoice_batches.id`).
- Domain surface in `app/Domain/Invoices`: `InvoiceBatch` (command object), `InvoiceBatchId`, `InvoiceBatchStatus`, `InvoiceBatchRepository`, `InvoiceBatchService`(+`Impl`), `InvoiceBatchGenerator`(+`Impl`), `InvoiceBatchEmailData`, event `Events\InvoiceBatchClosed`, listener `Listeners\QueueInvoiceEmails`, jobs `Jobs\FinishInvoiceBatchGeneration` + `Jobs\SendInvoiceBatchCreatedEmail`, mailable `Mails\InvoiceBatchCreatedMail`.
- Filament resource `app/Filament/Admin/Resources/InvoiceBatches/**` plus `app/Filament/Admin/Labels/InvoiceBatchStatusLabels`.
- Authorization: 6 `ResourcePermission` cases (`view_any_invoice_batches`, `view_invoice_batches`, `create_invoice_batches`, `update_invoice_batches`, `delete_invoice_batches`, `delete_any_invoice_batches`) granted by `RolePermissionSeeder`.
- Console command `app:generate-invoice-batch` (`App\Console\Commands\GenerateInvoiceBatchCommand`), scheduled monthly in `routes/console.php`.
- Dutch label `invoice_batch` / `invoice_batches` → "Facturatieronde(n)".
- Tests, factories, mailbook route, and the domain docs (`/.agents/docs/**`, `docs/architecture.md`) all reference the `InvoiceBatch` name.

## Summary of changes

A single mechanical rename applied consistently across code, database schema, permissions, translations, views, tests, and documentation. Identifiers are renamed with four case rules; files are renamed with `git mv`; the database is migrated with a table + column + permission-name migration; authorization permissions are renamed in place so existing role grants survive.

Decisions fixed before planning (do not re-litigate):

- **Full rename** — every `InvoiceBatch`/`invoice_batch`/`invoiceBatch`/`invoice-batch` token becomes the `SepaBatch`/`sepa_batch`/`sepaBatch`/`sepa-batch` equivalent, including invoice-generation helpers (`GenerateInvoiceBatchCommand`, `InvoiceBatchGenerator`, `addOpenInvoicesFromBatchMonth`, `InvoiceBatchCreatedMail`).
- **Permissions and Dutch label renamed too** — `*_invoice_batches` → `*_sepa_batches`; "Facturatieronde" → "SEPA ronde".
- **Database migrated, not edited** — existing migrations are left untouched; a new migration renames the table, the FK column, and the permission rows.
- **Existing migrations are not edited** so this works for both fresh installs and already-migrated databases.

---

## Identifier mapping

Apply these four ordered, mutually-exclusive replacements to every tracked file (they also handle plurals and the `_id` suffix automatically — e.g. `InvoiceBatch` + `es` → `SepaBatches`, `invoice_batch` + `_id` → `sepa_batch_id`):

```
InvoiceBatch  ->  SepaBatch
invoice_batch ->  sepa_batch
invoiceBatch  ->  sepaBatch
invoice-batch ->  sepa-batch
```

A safe `sed`/`perl` one-liner over the file set:

```bash
git grep -l -E 'InvoiceBatch|invoice_batch|invoiceBatch|invoice-batch' \
  -- ':!.spec/*' ':!.idea/*' \
  | while read -r f; do
      sed -i '' \
        -e 's/InvoiceBatch/SepaBatch/g' \
        -e 's/invoice_batch/sepa_batch/g' \
        -e 's/invoiceBatch/sepaBatch/g' \
        -e 's/invoice-batch/sepa-batch/g' \
        "$f"
    done
```

Exclude `.spec/` (historical planning notes) and `.idea/`. Do **not** run it over the `database/migrations/` files (see below).

---

## File renames

Rename files/directories with `git mv` (preserves history). Directories first:

```bash
git mv app/Filament/Admin/Resources/InvoiceBatches app/Filament/Admin/Resources/SepaBatches
git mv tests/Feature/Filament/InvoiceBatches tests/Feature/Filament/SepaBatches
git mv resources/views/filament/admin/resources/invoice-batches resources/views/filament/admin/resources/sepa-batches
```

Then the individual files:

```bash
# Resource files (now under SepaBatches)
git mv app/Filament/Admin/Resources/SepaBatches/InvoiceBatchResource.php app/Filament/Admin/Resources/SepaBatches/SepaBatchResource.php
git mv app/Filament/Admin/Resources/SepaBatches/Pages/ListInvoiceBatches.php app/Filament/Admin/Resources/SepaBatches/Pages/ListSepaBatches.php
git mv app/Filament/Admin/Resources/SepaBatches/Pages/EditInvoiceBatch.php app/Filament/Admin/Resources/SepaBatches/Pages/EditSepaBatch.php
git mv app/Filament/Admin/Resources/SepaBatches/Pages/CreateInvoiceBatch.php app/Filament/Admin/Resources/SepaBatches/Pages/CreateSepaBatch.php
git mv app/Filament/Admin/Resources/SepaBatches/Tables/InvoiceBatchesTable.php app/Filament/Admin/Resources/SepaBatches/Tables/SepaBatchesTable.php
git mv app/Filament/Admin/Resources/SepaBatches/Schemas/InvoiceBatchForm.php app/Filament/Admin/Resources/SepaBatches/Schemas/SepaBatchForm.php
git mv app/Filament/Admin/Resources/SepaBatches/RelationManagers/InvoiceBatchInvoicesRelationManager.php app/Filament/Admin/Resources/SepaBatches/RelationManagers/SepaBatchInvoicesRelationManager.php
git mv app/Filament/Admin/Resources/SepaBatches/Widgets/InvoiceBatchGenerationProgress.php app/Filament/Admin/Resources/SepaBatches/Widgets/SepaBatchGenerationProgress.php
git mv app/Filament/Admin/Resources/SepaBatches/Helpers/OnOpenInvoiceBatch.php app/Filament/Admin/Resources/SepaBatches/Helpers/OnOpenSepaBatch.php
git mv app/Filament/Admin/Resources/SepaBatches/Actions/GenerateInvoiceBatchAction.php app/Filament/Admin/Resources/SepaBatches/Actions/GenerateSepaBatchAction.php

# Resource tests
git mv tests/Feature/Filament/SepaBatches/InvoiceBatchResourceTest.php tests/Feature/Filament/SepaBatches/SepaBatchResourceTest.php
git mv tests/Feature/Filament/SepaBatches/InvoiceBatchGenerationProgressTest.php tests/Feature/Filament/SepaBatches/SepaBatchGenerationProgressTest.php

# View
git mv resources/views/filament/admin/resources/sepa-batches/widgets/invoice-batch-generation-progress.blade.php resources/views/filament/admin/resources/sepa-batches/widgets/sepa-batch-generation-progress.blade.php

# Models / policies / command / labels
git mv app/Models/InvoiceBatch.php app/Models/SepaBatch.php
git mv app/Policies/InvoiceBatchPolicy.php app/Policies/SepaBatchPolicy.php
git mv app/Console/Commands/GenerateInvoiceBatchCommand.php app/Console/Commands/GenerateSepaBatchCommand.php
git mv app/Filament/Admin/Labels/InvoiceBatchStatusLabels.php app/Filament/Admin/Labels/SepaBatchStatusLabels.php

# Domain — Invoices
git mv app/Domain/Invoices/InvoiceBatch.php app/Domain/Invoices/SepaBatch.php
git mv app/Domain/Invoices/InvoiceBatchEmailData.php app/Domain/Invoices/SepaBatchEmailData.php
git mv app/Domain/Invoices/InvoiceBatchGenerator.php app/Domain/Invoices/SepaBatchGenerator.php
git mv app/Domain/Invoices/InvoiceBatchGeneratorImpl.php app/Domain/Invoices/SepaBatchGeneratorImpl.php
git mv app/Domain/Invoices/InvoiceBatchId.php app/Domain/Invoices/SepaBatchId.php
git mv app/Domain/Invoices/InvoiceBatchRepository.php app/Domain/Invoices/SepaBatchRepository.php
git mv app/Domain/Invoices/InvoiceBatchService.php app/Domain/Invoices/SepaBatchService.php
git mv app/Domain/Invoices/InvoiceBatchServiceImpl.php app/Domain/Invoices/SepaBatchServiceImpl.php
git mv app/Domain/Invoices/InvoiceBatchStatus.php app/Domain/Invoices/SepaBatchStatus.php
git mv app/Domain/Invoices/Events/InvoiceBatchClosed.php app/Domain/Invoices/Events/SepaBatchClosed.php
git mv app/Domain/Invoices/Jobs/FinishInvoiceBatchGeneration.php app/Domain/Invoices/Jobs/FinishSepaBatchGeneration.php
git mv app/Domain/Invoices/Jobs/SendInvoiceBatchCreatedEmail.php app/Domain/Invoices/Jobs/SendSepaBatchCreatedEmail.php
git mv app/Domain/Invoices/Mails/InvoiceBatchCreatedMail.php app/Domain/Invoices/Mails/SepaBatchCreatedMail.php

# Infrastructure
git mv app/Infrastructure/Invoices/InvoiceBatchRepositoryDb.php app/Infrastructure/Invoices/SepaBatchRepositoryDb.php

# Factories + view
git mv database/factories/InvoiceBatchFactory.php database/factories/SepaBatchFactory.php
git mv resources/views/mail/invoice-batch-created.blade.php resources/views/mail/sepa-batch-created.blade.php

# Tests
git mv tests/Feature/Infrastructure/Invoices/InvoiceBatchRepositoryDbTest.php tests/Feature/Infrastructure/Invoices/SepaBatchRepositoryDbTest.php
git mv tests/Feature/Infrastructure/Invoices/InvoiceBatchRepositoryExpectation.php tests/Feature/Infrastructure/Invoices/SepaBatchRepositoryExpectation.php
git mv tests/Unit/Domain/Invoices/Events/InvoiceBatchClosedTest.php tests/Unit/Domain/Invoices/Events/SepaBatchClosedTest.php
git mv tests/Unit/Domain/Invoices/InvoiceBatchEmailDataTest.php tests/Unit/Domain/Invoices/SepaBatchEmailDataTest.php
git mv tests/Unit/Domain/Invoices/InvoiceBatchGeneratorImplTest.php tests/Unit/Domain/Invoices/SepaBatchGeneratorImplTest.php
git mv tests/Unit/Domain/Invoices/InvoiceBatchIdTest.php tests/Unit/Domain/Invoices/SepaBatchIdTest.php
git mv tests/Unit/Domain/Invoices/InvoiceBatchRepositoryExpectation.php tests/Unit/Domain/Invoices/SepaBatchRepositoryExpectation.php
git mv tests/Unit/Domain/Invoices/InvoiceBatchServiceExpectation.php tests/Unit/Domain/Invoices/SepaBatchServiceExpectation.php
git mv tests/Unit/Domain/Invoices/InvoiceBatchServiceImplTest.php tests/Unit/Domain/Invoices/SepaBatchServiceImplTest.php
git mv tests/Unit/Domain/Invoices/InvoiceBatchServiceTest.php tests/Unit/Domain/Invoices/SepaBatchServiceTest.php
git mv tests/Unit/Domain/Invoices/InvoiceBatchTest.php tests/Unit/Domain/Invoices/SepaBatchTest.php
git mv tests/Unit/Domain/Invoices/Jobs/FinishInvoiceBatchGenerationTest.php tests/Unit/Domain/Invoices/Jobs/FinishSepaBatchGenerationTest.php
git mv tests/Unit/Domain/Invoices/Jobs/SendInvoiceBatchCreatedEmailTest.php tests/Unit/Domain/Invoices/Jobs/SendSepaBatchCreatedEmailTest.php
git mv tests/Unit/Domain/Invoices/Mails/InvoiceBatchCreatedMailTest.php tests/Unit/Domain/Invoices/Mails/SepaBatchCreatedMailTest.php
```

Files that reference the concept but keep their current filename (the `sed` rules handle their contents): `app/Models/Invoice.php`, `app/Infrastructure/Invoices/InvoiceMailRepositoryDb.php`, `app/Infrastructure/Invoices/InvoiceRepositoryDb.php`, `app/Domain/Invoices/{InvoiceGeneratorImpl,InvoiceTarget,NewInvoice,SepaExportService}.php`, `app/Domain/Bookkeeping/BookkeepingRecordRepository.php`, `app/Infrastructure/Bookkeeping/BookkeepingRecordDbRepository.php`, `app/Domain/Authorization/ResourcePermission.php`, `database/seeders/RolePermissionSeeder.php`, `database/factories/InvoiceFactory.php`, `routes/console.php`, `routes/mailbook.php`, `lang/nl/labels.php`, `docs/architecture.md`, and the docs under `/.agents/docs/`.

---

## Database migration

Create `database/migrations/2026_10_17_000000_rename_invoice_batches_to_sepa_batches.php`. It renames the table, the FK column on `invoices`, and the six permission rows. Existing migrations are left untouched, so fresh installs still build the old schema first and then rename it here; already-migrated databases only run this file.

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
        // Drop the FK first so the column can be renamed cleanly.
        Schema::table('invoices', static function (Blueprint $table): void {
            $table->dropForeign(['invoice_batch_id']);
        });

        Schema::table('invoices', static function (Blueprint $table): void {
            $table->renameColumn('invoice_batch_id', 'sepa_batch_id');
        });

        Schema::rename('invoice_batches', 'sepa_batches');

        Schema::table('invoices', static function (Blueprint $table): void {
            $table->foreign('sepa_batch_id')->references('id')->on('sepa_batches')->nullOnDelete();
        });

        $permissions = [
            'view_any_invoice_batches' => 'view_any_sepa_batches',
            'view_invoice_batches' => 'view_sepa_batches',
            'create_invoice_batches' => 'create_sepa_batches',
            'update_invoice_batches' => 'update_sepa_batches',
            'delete_invoice_batches' => 'delete_sepa_batches',
            'delete_any_invoice_batches' => 'delete_any_sepa_batches',
        ];

        foreach ($permissions as $old => $new) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new]);
        }
    }

    public function down(): void
    {
        $permissions = [
            'view_any_sepa_batches' => 'view_any_invoice_batches',
            'view_sepa_batches' => 'view_invoice_batches',
            'create_sepa_batches' => 'create_invoice_batches',
            'update_sepa_batches' => 'update_invoice_batches',
            'delete_sepa_batches' => 'delete_invoice_batches',
            'delete_any_sepa_batches' => 'delete_any_invoice_batches',
        ];

        foreach ($permissions as $old => $new) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new]);
        }

        Schema::table('invoices', static function (Blueprint $table): void {
            $table->dropForeign(['sepa_batch_id']);
        });

        Schema::rename('sepa_batches', 'invoice_batches');

        Schema::table('invoices', static function (Blueprint $table): void {
            $table->renameColumn('sepa_batch_id', 'invoice_batch_id');
        });

        Schema::table('invoices', static function (Blueprint $table): void {
            $table->foreign('invoice_batch_id')->references('id')->on('invoice_batches')->nullOnDelete();
        });
    }
};
```

The `permissions` table exists at migration time (Spatie's `create_permission_tables` runs earlier). On a fresh test database it is empty, so the `update` is a no-op.

---

## Domain layer

After the `sed` pass, the domain surface reads `SepaBatch*`. Representative before → after:

`app/Domain/Invoices/SepaBatchService.php` (was `InvoiceBatchService`):

```php
#[Autowire]
interface SepaBatchService
{
    public function createBatch(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate): SepaBatchId;

    public function attachBatchMonth(SepaBatchId $batchId): int;

    public function startGeneration(SepaBatchId $batchId, int $expectedInvoices): void;

    public function finishGeneration(SepaBatchId $batchId): void;

    public function closeBatch(SepaBatchId $batchId): void;

    public function completeBatch(SepaBatchId $batchId): void;
}
```

`app/Domain/Invoices/SepaBatchServiceImpl.php` — `closeBatch` dispatches the renamed event:

```php
$this->eventDispatcher->dispatch(new SepaBatchClosed(batchId: $batchId));
```

`app/Domain/Invoices/SepaBatchRepository.php` — only the typed-ID parameter changes; the method name is unchanged because it contains no `InvoiceBatch` token (its "Batch" is the generic word, like `closeBatch`/`completeBatch`):

```php
#[Autowire]
interface SepaBatchRepository
{
    public function create(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate, SepaBatchStatus $status): SepaBatchId;

    public function addOpenInvoicesFromBatchMonth(SepaBatchId $batchId): int;

    public function markGenerationStarted(SepaBatchId $batchId, int $expectedInvoices): void;

    public function markGenerationFinished(SepaBatchId $batchId): void;

    /** @return list<SepaExportInvoice> */
    public function getInvoicesForExport(SepaBatchId $batchId): array;

    public function markInvoicesAsPending(SepaBatchId $batchId): void;

    public function closeBatch(SepaBatchId $batchId): void;

    public function completeBatch(SepaBatchId $batchId): void;

    /** @return list<InvoiceId> */
    public function getPendingInvoicesForBatch(SepaBatchId $batchId): array;

    public function getSepaTransferDate(SepaBatchId $batchId): DateTimeInterface;

    public function getBatchEmailData(SepaBatchId $batchId): SepaBatchEmailData;

    public function attachInvoice(SepaBatchId $batchId, InvoiceId $id): void;
}
```

`app/Domain/Invoices/SepaBatchGeneratorImpl.php` — the job-batch name is updated by the kebab rule (`invoice-batch-...` → `sepa-batch-...`):

```php
$batchName = sprintf('sepa-batch-%s-%s', $sepaBatch->invoiceDate->format('Y-m-d'), $batchId->value);
```

`app/Domain/Bookkeeping/BookkeepingRecordRepository.php` — the method signature uses the renamed typed ID:

```php
public function createForBatch(SepaBatchId $batchId): void;
```

The listener (`Listeners/QueueInvoiceEmails.php`) and jobs keep their own names (they contain no `InvoiceBatch` token) but now type against `SepaBatchClosed` / `SepaBatchRepository` / `SepaBatchId`.

---

## Model layer

`app/Models/SepaBatch.php` (was `InvoiceBatch.php`) — class, `use`, `@property` and `casts()` are updated by `sed`; the Eloquent table name follows the class name automatically (`sepa_batches`), so no explicit `$table` is needed.

`app/Models/Invoice.php` — the relationship is renamed (camelCase rule):

```php
/** @return BelongsTo<SepaBatch, $this> */
public function sepaBatch(): BelongsTo
{
    return $this->belongsTo(SepaBatch::class);
}
```

---

## Infrastructure layer

`app/Infrastructure/Invoices/SepaBatchRepositoryDb.php` — every `InvoiceBatch` model reference, `invoice_batch_id` column, and `invoice_batches` table reference becomes the `SepaBatch`/`sepa_batch_id`/`sepa_batches` equivalent. Example:

```php
public function getInvoicesForExport(SepaBatchId $batchId): array
{
    $invoices = Invoice::query()
        ->where('sepa_batch_id', $batchId->value)
        // ... unchanged joins/selects ...
        ->get();
}
```

```php
$row = DB::table('sepa_batches')
    ->where('sepa_batches.id', $batchId->value)
    ->join('invoices', 'invoices.sepa_batch_id', '=', 'sepa_batches.id')
    // ...
```

`app/Infrastructure/Invoices/InvoiceMailRepositoryDb.php` — the eager-load and accessor are renamed:

```php
->with(['member.paymentInformation', 'lines', 'sepaBatch'])

sepaTransferDate: $invoice->member?->paymentInformation?->mandate_accepted_date !== null ? $invoice->sepaBatch?->sepa_transfer_date : null,
```

`app/Infrastructure/Invoices/InvoiceRepositoryDb.php` — the fill column is renamed:

```php
'sepa_batch_id' => $invoice->batchId?->value,
```

`app/Infrastructure/Bookkeeping/BookkeepingRecordDbRepository.php` — the `invoice_batches` join/table references become `sepa_batches` (both in `createForBatch` and the `EXTRACT`/`STRFTIME` year expressions).

`app/Infrastructure/Invoices/SepaExportServiceImpl.php` — `use` and constructor type change to `SepaBatchId` / `SepaBatchRepository`.

---

## Filament layer

The resource, pages, table, form, action, widgets and relation manager all move to the `SepaBatches` namespace with `SepaBatch`-named classes. Representative changes:

`app/Filament/Admin/Resources/SepaBatches/SepaBatchResource.php`:

```php
protected static ?string $model = SepaBatch::class;

public static function table(Table $table): Table
{
    return SepaBatchesTable::configure($table);
}

public static function form(Schema $schema): Schema
{
    return SepaBatchForm::configure($schema);
}

public static function getPluralLabel(): string
{
    return __('labels.sepa_batches');
}

public static function getLabel(): string
{
    return __('labels.sepa_batch');
}

public static function getRecordTitle(?Model $record): string
{
    Assert::nullOrIsInstanceOf($record, SepaBatch::class);
    return $record === null ? 'N/A' : 'SEPA ronde ' . $record->id;
}
```

`app/Filament/Admin/Resources/SepaBatches/Actions/GenerateSepaBatchAction.php` — action id and command object:

```php
return Action::make('generate_sepa_batch')
    ->label(__('labels.generate_sepa_batch'))
    ->visible(static fn (): bool => auth()->user()?->can('create', SepaBatchModel::class) ?? false)
    ->action(static function (ListRecords $livewire, array $data, SepaBatchGenerator $generator): void {
        $batchId = $generator->generate(new SepaBatch(
            invoiceDate: CarbonImmutable::parse($data['invoice_date']),
            sepaTransferDate: CarbonImmutable::parse($data['sepa_transfer_date']),
        ));

        $livewire->redirect(SepaBatchResource::getUrl('edit', ['record' => $batchId->value]));
    })
```

The `EditSepaBatch` page keeps its existing actions (`addInvoices`, `closeBatch`, `completeBatch`, `exportSepa`) — only type hints and imports change. Its `exportSepa` action and the purchase-order work are extended in the companion plan.

---

## Authorization

`app/Domain/Authorization/ResourcePermission.php` — the six cases are renamed (case names and string values):

```php
// Sepa Batches
case ViewAnySepaBatches = 'view_any_sepa_batches';
case ViewSepaBatches = 'view_sepa_batches';
case CreateSepaBatches = 'create_sepa_batches';
case UpdateSepaBatches = 'update_sepa_batches';
case DeleteSepaBatches = 'delete_sepa_batches';
case DeleteAnySepaBatches = 'delete_any_sepa_batches';
```

`database/seeders/RolePermissionSeeder.php` — `allPermissionsFor('invoice_batches')` becomes `allPermissionsFor('sepa_batches')`.

`app/Policies/SepaBatchPolicy.php` — `permissionPrefix()` returns `'sepa_batches'`; all other logic unchanged.

---

## Console command + routes

`app/Console/Commands/GenerateSepaBatchCommand.php` — the command signature is renamed by the kebab rule:

```php
#[Signature('app:generate-sepa-batch {date?} {sepaTransferDate?}')]
final class GenerateSepaBatchCommand extends Command
{
    public function handle(SepaBatchGenerator $sepaBatchGenerator): void
    {
        $command = new SepaBatch(
            invoiceDate: $this->parseInvoiceDate($date),
            sepaTransferDate: $this->parseSepaTransferDate($date),
        );

        $sepaBatchGenerator->generate($command);
    }
}
```

`routes/console.php` — schedule the renamed class:

```php
Schedule::command(GenerateSepaBatchCommand::class)->monthlyOn(1, '04:00');
```

`routes/mailbook.php` — update the `use` statements and the `SepaBatchCreatedMail` / `SepaBatchEmailData` / `SepaBatchId` references.

---

## Translations

`lang/nl/labels.php` — rename keys and update the Dutch copy:

```php
'sepa_batch' => 'SEPA ronde',
'sepa_batches' => 'SEPA rondes',
'generate_sepa_batch' => 'SEPA ronde genereren',
```

`app/Domain/Invoices/Mails/SepaBatchCreatedMail.php` — the hardcoded subject still reads "Nieuwe facturatieronde aangemaakt"; update to "Nieuwe SEPA ronde aangemaakt" for consistency (two occurrences: `envelope()` and `subject()`).

The Blade view `resources/views/mail/sepa-batch-created.blade.php` is renamed; its `markdown:` reference (`'mail.sepa-batch-created'`) is updated by the kebab rule.

---

## Tests

All test files are renamed (see the `git mv` list) and their contents rewritten by the `sed` rules. Assertions against the `invoice_batches` table become `sepa_batches`; `invoice_batch_id` becomes `sepa_batch_id`; model/class/namespace references become `SepaBatch*`.

`tests/Feature/Authorization/AuthorizationTest.php` — `use App\Filament\Admin\Resources\SepaBatches\Pages\ListSepaBatches;` and the `test_invoicing_can_view_sepa_batches` method.

`tests/Feature/Infrastructure/Bookkeeping/BookkeepingRecordDbRepositoryTest.php`, `tests/Feature/Infrastructure/Invoices/InvoiceMailRepositoryDbTest.php`, `tests/Feature/Infrastructure/Invoices/InvoiceRepositoryDbTest.php`, `tests/Feature/Infrastructure/Invoices/SepaExportServiceImplTest.php`, and the unit tests under `tests/Unit/Domain/Invoices/**` all reference the renamed classes/types automatically via the `sed` pass.

---

## Documentation

Update the term name in the domain docs so the model and the code agree:

`/.agents/docs/GLOSSARY.md` — rename the `InvoiceBatch` term to `SepaBatch`; keep the definition for now (it is expanded to mention purchase orders in the companion plan).

`/.agents/docs/invoicing/CONTEXT.md` — rename `InvoiceBatch` and `InvoiceBatchStatus` to `SepaBatch`/`SepaBatchStatus`.

`/.agents/docs/CONTEXT-MAP.md` — update the `SepaExportService` external-partner line to say "per closed SepaBatch" instead of "per closed InvoiceBatch".

`docs/architecture.md` — `InvoiceBatchGeneratorImpl` → `SepaBatchGeneratorImpl`.

---

## Verification

Run the full suite after the rename (nothing else should change):

```bash
./Taskfile artisan test --compact
```

If a stray old token remains, find it with:

```bash
git grep -n -E 'InvoiceBatch|invoice_batch|invoiceBatch|invoice-batch' -- ':!.spec/*' ':!.idea/*'
```

The only allowed remaining matches are inside `.spec/` historical notes (which are intentionally not renamed).
