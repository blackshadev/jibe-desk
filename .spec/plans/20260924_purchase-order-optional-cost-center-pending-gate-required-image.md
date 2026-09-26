# Purchase order: optional cost center, completeness gate on Pending/Paid, required image

Date: 2026-09-24

## Overview

Three changes to the Purchase Orders context:

- The cost center on a purchase order line becomes **optional** when creating/editing a purchase order (form + database).
- When a purchase order is **marked as pending**, it is validated: every line must have a cost center that **exists**, and the creditor information (`creditor_name`, `creditor_iban`) must be filled. The same gate is applied to the **paid** transition, because bookkeeping records are written on both transitions and a purchase order can reach `Paid` without passing `Pending` (bank transaction completion attaches and pays open purchase orders).
- The purchase order **image becomes required** in both purchase-order creation forms (purchase order form + "create purchase order from bank transaction" action).

## Current situation

Hexagonal Laravel 13 + Filament v5 app: pure domain in `app/Domain/PurchaseOrders/`, Eloquent adapter in `app/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDb.php`, UI in `app/Filament/Admin/Resources/PurchaseOrders/`. Dev/prod DB is pgsql (`.env`), tests run sqlite `:memory:` (`phpunit.xml`).

Relevant facts found during research:

- `purchase_order_lines.cost_center_id` is a non-nullable FK (`database/migrations/2026_06_26_203537_create_purchase_order_lines_table.php:18`). Making it optional requires a migration (new alter migrations are the established convention, cf. `2026_09_24_091647_add_member_id_to_purchase_orders_table.php`).
- `Schemas/PurchaseOrderForm.php:142-147` marks the line `cost_center_id` Select `->required()`; `:58-66` has `FileUpload::make('image_path')` without `->required()`. `purchase_orders.image_path` is nullable in the DB and stays nullable (legacy rows), only the form becomes required.
- There is a **second creation path**: `app/Filament/Admin/Resources/BankTransactions/Actions/CreatePurchaseOrderFromTransactionAction.php` — own modal schema with `->required()` cost center Select (`:85-90`) and an optional `image_path` FileUpload (`:76-84`) that is **never persisted** (`PurchaseOrder::create(...)` at `:96-102` omits `image_path` — pre-existing bug that must be fixed once the image is required).
- Status transitions: `PurchaseOrderServiceImpl` (`app/Domain/PurchaseOrders/PurchaseOrderServiceImpl.php`) delegates to `PurchaseOrderRepository::markAsPending|markAsPaid|markAsDeclined` (bulk `whereIn` updates in `PurchaseOrderRepositoryDb`) and then `BookkeepingRecordRepository::createForPurchaseOrder` (`app/Infrastructure/Bookkeeping/BookkeepingRecordDbRepository.php:66-103`) which groups lines **by `purchase_order_lines.cost_center_id`** and inserts `bookkeeping_records.cost_center_id` rows — NULL cost centers would corrupt/crash bookkeeping creation (`bookkeeping_records.cost_center_id` is a non-null FK).
- `markAsPending` callers: `PurchaseOrderStateActions` (`Actions/PurchaseOrderStateActions.php:22-35`, used on `EditPurchaseOrder` and `ViewPurchaseOrder`) and `BankTransactionServiceImpl::unlinkReversal` (`app/Domain/BankTransactions/BankTransactionServiceImpl.php:100`, UI: `UnlinkReversalAction`). `markAsPaid` callers: `PurchaseOrderStateActions` (`:37-47`) and `BankTransactionServiceImpl::complete` (`:41`, UI: `CompleteBankTransactionAction`, `CompleteAllMatchedAction` in `BankStatements/Actions`). `BankTransactionServiceImpl::complete()` marks **whatever status** the purchase order has straight to `Paid`.
- Domain exceptions precedent: `UnknownBankAccountException` (`app/Domain/BankTransactions/UnknownBankAccountException.php`) — `final class ... extends RuntimeException` with promoted `public readonly` data; `InvoiceBatchRepositoryDb::completeBatch` throws `DomainException` on invalid transitions. UI handling precedent: `ImportMt940Action` catches the domain exception and sends a `Notification::make()->danger()`; `EditInvoiceBatch::completeBatch` uses `$action->failure()` + `failureNotificationTitle`.
- Labels/mapping precedent: `app/Filament/Admin/Labels/PurchaseOrderStatusLabels.php` maps a domain enum to `__('labels...')` strings. All UI strings live in `lang/nl/labels.php` and `lang/nl/notifications.php`.
- Tests: PHPUnit 12. Unit tests use Mockery Expectation wrapper classes (`tests/Unit/Domain/PurchaseOrders/PurchaseOrderRepositoryExpectation.php`, `PurchaseOrderServiceExpectation.php`); feature tests use `Livewire::test(...)->fillForm([...])->call(...)` in `tests/Feature/Filament/Admin/Resources/PurchaseOrderResourceTest.php` and `tests/Feature/Filament/BankTransaction/BankTransactionResourceTest.php`. `PurchaseOrderFactory` fills `creditor_name`/`creditor_iban` by default; `PurchaseOrderLineFactory` defaults `cost_center_id` to `CostCenter::factory()`.
- `app/Filament/Admin/Resources/Members/RelationManagers/PurchaseOrdersRelationManager.php:58-61` only links to the create page (no separate form), so it is not affected beyond the shared form.

## Planned changes

- One migration making `purchase_order_lines.cost_center_id` nullable.
- Domain: new readonly completeness DTOs (`PurchaseOrderCompleteness`, `PurchaseOrderLineCompleteness`), a structured problem value (`PurchaseOrderProblem` + `PurchaseOrderProblemType` enum), and `PurchaseOrderIncompleteException`. `PurchaseOrderRepository` gains a `getCompleteness(PurchaseOrderIdList)` read port; `PurchaseOrderServiceImpl` guards `markAsPending` and `markAsPaid` with a pure `assertComplete()` rule before any writes.
- Infrastructure: `PurchaseOrderRepositoryDb::getCompleteness()` (loads purchase orders + lines and checks cost center existence).
- Filament: form changes (cost center optional, image required), same for the bank-transaction creation action (plus persisting the uploaded image), and danger notifications on all transition callers instead of 500s.
- Labels, notifications, factory states, unit + feature tests, and a small update to the purchase-orders context doc.

## Database migration

Create with `./Taskfile artisan make:migration --no-interaction make_cost_center_id_nullable_in_purchase_order_lines_table` (works on pgsql and sqlite; `->change()` keeps the existing FK and index).

`database/migrations/xxxx_make_cost_center_id_nullable_in_purchase_order_lines_table.php`:

```php
public function up(): void
{
    Schema::table('purchase_order_lines', static function (Blueprint $table): void {
        $table->foreignId('cost_center_id')->nullable()->change();
    });
}

public function down(): void
{
    Schema::table('purchase_order_lines', static function (Blueprint $table): void {
        $table->foreignId('cost_center_id')->nullable(false)->change();
    });
}
```

Do **not** edit `2026_06_26_203537_create_purchase_order_lines_table.php`; new alter migrations are the convention in this repo.

## Domain — completeness data and problems

New files in `app/Domain/PurchaseOrders/` (pure, no framework imports, `final readonly` DTOs — same style as `App\Domain\BankTransactions\TransactionMatchCriteria` and `UnknownBankAccountException`).

`PurchaseOrderLineCompleteness.php`:

```php
final readonly class PurchaseOrderLineCompleteness
{
    public function __construct(
        public ?int $costCenterId,
        public bool $costCenterExists,
    ) {}
}
```

`PurchaseOrderCompleteness.php`:

```php
final readonly class PurchaseOrderCompleteness
{
    /**
     * @param list<PurchaseOrderLineCompleteness> $lines
     */
    public function __construct(
        public PurchaseOrderId $id,
        public ?string $creditorName,
        public ?string $creditorIban,
        public array $lines,
    ) {}
}
```

`PurchaseOrderProblemType.php` (TitleCase enum keys per PHP conventions, snake_case backing values like `PurchaseOrderStatus`):

```php
enum PurchaseOrderProblemType: string
{
    case MissingCostCenter = 'missing_cost_center';
    case UnknownCostCenter = 'unknown_cost_center';
    case MissingCreditorName = 'missing_creditor_name';
    case MissingCreditorIban = 'missing_creditor_iban';
}
```

`PurchaseOrderProblem.php` (`$lineNumber` is 1-based, null for header-level problems):

```php
final readonly class PurchaseOrderProblem
{
    public function __construct(
        public PurchaseOrderId $purchaseOrderId,
        public PurchaseOrderProblemType $type,
        public ?int $lineNumber = null,
    ) {}
}
```

`PurchaseOrderIncompleteException.php` (mirrors `UnknownBankAccountException`):

```php
final class PurchaseOrderIncompleteException extends RuntimeException
{
    /** @param list<PurchaseOrderProblem> $problems */
    public function __construct(
        public readonly array $problems,
    ) {
        parent::__construct('Purchase order is incomplete and cannot change status.');
    }
}
```

## Domain — repository port and service guard

`app/Domain/PurchaseOrders/PurchaseOrderRepository.php` — add a read port (fact-only, rules stay in the domain):

```php
/**
 * Load the facts needed to check purchase order completeness for a status transition.
 *
 * @return list<PurchaseOrderCompleteness>
 */
public function getCompleteness(PurchaseOrderIdList $ids): array;
```

`app/Domain/PurchaseOrders/PurchaseOrderServiceImpl.php` — guard **before any write** so a failed transition leaves the purchase order untouched (important: `markAsPaid` is also called from `BankTransactionServiceImpl::complete()`):

```php
#[Override]
public function markAsPending(PurchaseOrderIdList $ids): void
{
    $this->assertComplete($this->repository->getCompleteness($ids));

    $this->repository->markAsPending($ids);
    $this->bookkeepingRepository->createForPurchaseOrder($ids);
}

#[Override]
public function markAsPaid(PurchaseOrderIdList $ids): void
{
    $this->assertComplete($this->repository->getCompleteness($ids));

    $this->repository->markAsPaid($ids);
    $this->bookkeepingRepository->createForPurchaseOrder($ids);
}

/**
 * @param list<PurchaseOrderCompleteness> $completenessList
 *
 * @throws PurchaseOrderIncompleteException
 */
private function assertComplete(array $completenessList): void
{
    $problems = [];

    foreach ($completenessList as $completeness) {
        if ($completeness->creditorName === null || trim($completeness->creditorName) === '') {
            $problems[] = new PurchaseOrderProblem($completeness->id, PurchaseOrderProblemType::MissingCreditorName);
        }

        if ($completeness->creditorIban === null || trim($completeness->creditorIban) === '') {
            $problems[] = new PurchaseOrderProblem($completeness->id, PurchaseOrderProblemType::MissingCreditorIban);
        }

        foreach (array_values($completeness->lines) as $index => $line) {
            if ($line->costCenterId === null) {
                $problems[] = new PurchaseOrderProblem($completeness->id, PurchaseOrderProblemType::MissingCostCenter, $index + 1);
            } elseif (!$line->costCenterExists) {
                $problems[] = new PurchaseOrderProblem($completeness->id, PurchaseOrderProblemType::UnknownCostCenter, $index + 1);
            }
        }
    }

    if ($problems !== []) {
        throw new PurchaseOrderIncompleteException($problems);
    }
}
```

Rule recap: per line `cost_center_id` filled **and** existing in `cost_centers`; header `creditor_name` + `creditor_iban` filled (whitespace counts as empty). `markAsDeclined` stays ungated. The image is **not** part of this gate (it is enforced at form level).

## Infrastructure — repository implementation

`app/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDb.php` — implement `getCompleteness` (one query for purchase orders + lines, one for existing cost center ids — no N+1):

```php
#[Override]
public function getCompleteness(PurchaseOrderIdList $ids): array
{
    $idValues = array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids);

    $existingCostCenterIds = CostCenter::query()->pluck('id')->all();

    return PurchaseOrder::query()
        ->with('lines')
        ->whereIn('id', $idValues)
        ->get()
        ->map(static function (PurchaseOrder $purchaseOrder) use ($existingCostCenterIds): PurchaseOrderCompleteness {
            return new PurchaseOrderCompleteness(
                id: PurchaseOrderId::create($purchaseOrder->id),
                creditorName: $purchaseOrder->creditor_name,
                creditorIban: $purchaseOrder->creditor_iban,
                lines: $purchaseOrder->lines
                    ->map(static fn (PurchaseOrderLine $line) => new PurchaseOrderLineCompleteness(
                        costCenterId: $line->cost_center_id,
                        costCenterExists: $line->cost_center_id !== null
                            && in_array($line->cost_center_id, $existingCostCenterIds, true),
                    ))
                    ->values()
                    ->all(),
            );
        })
        ->all();
}
```

(If the `cost_centers` table is large, replace the full `pluck` with `CostCenter::query()->whereIn('id', ...)->pluck('id')` over the collected `cost_center_id`s of the loaded lines. Either variant is fine — match house preference for the smallest query.)

## Filament — purchase order form

`app/Filament/Admin/Resources/PurchaseOrders/Schemas/PurchaseOrderForm.php`:

- Make the image required (add `->required()`):

```php
FileUpload::make('image_path')
    ->label(__('labels.image'))
    ->image()
    ->imagePreviewHeight('250')
    ->directory('purchase-orders')
    ->disk('local')
    ->visibility('private')
    ->previewable()
    ->required()
    ->columnSpanFull(),
```

- Make the line cost center optional (drop `->required()`, add a hint that it is needed before the pending transition):

```php
Select::make('cost_center_id')
    ->label(__('labels.cost_center'))
    ->options(static fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
    ->searchable()
    ->preload()
    ->helperText(__('labels.cost_center_optional_hint')),
```

Everything else in the form (member prefill, `MemberCreditorPrefill`, VAT auto-calc) stays unchanged.

## Filament — purchase order from bank transaction action

`app/Filament/Admin/Resources/BankTransactions/Actions/CreatePurchaseOrderFromTransactionAction.php` — same rule set on the second creation path:

- `Select::make('cost_center_id')` (`:85-90`): drop `->required()`, add the same `->helperText(...)`.
- `FileUpload::make('image_path')` (`:76-84`): add `->required()`.
- **Persist the uploaded image** (currently dropped — required-ness makes this mandatory):

```php
$po = PurchaseOrder::create([
    'date' => $data['date'],
    'description' => $data['description'],
    'creditor_iban' => $data['creditor_iban'] ?? null,
    'creditor_name' => $data['creditor_name'] ?? null,
    'image_path' => $data['image_path'],
    'status' => PurchaseOrderStatus::Open,
]);

$po->lines()->create([
    'description' => $data['description'],
    'price' => $data['line_price'],
    'price_vat' => $data['line_price_vat'],
    'cost_center_id' => $data['cost_center_id'] ?? null,
]);
```

## Filament — transition action error handling

`app/Filament/Admin/Resources/PurchaseOrders/Actions/PurchaseOrderStateActions.php` — wrap `markAsPending` and `markAsPaid` actions so the gate surfaces as a danger notification instead of a 500 (pattern: `ImportMt940Action`). Do the same transform in both actions:

```php
->action(static function (PurchaseOrder $record, PurchaseOrderService $service): void {
    try {
        $service->markAsPending(PurchaseOrderIdList::fromArray([$record->id]));
    } catch (PurchaseOrderIncompleteException $exception) {
        Notification::make()
            ->title(__('notifications.purchase_order_incomplete'))
            ->body(PurchaseOrderProblemLabels::describeAll($exception->problems))
            ->danger()
            ->send();
    }
})
```

On failure the `successRedirectUrl` and success notification do not fire; the `->after(...)` dispatch only causes a harmless refresh.

## Filament — bank transaction action error handling

These callers hit the same gate and must not 500:

- `app/Filament/Admin/Resources/BankTransactions/Actions/CompleteBankTransactionAction.php` (`:25-27`): wrap `$service->complete(...)` in try/catch `PurchaseOrderIncompleteException` → `Notification::make()->title(__('notifications.purchase_order_incomplete'))->body(PurchaseOrderProblemLabels::describeAll($exception->problems))->danger()->send();` (the purchase order stays `Open`/`Pending` and can be fixed and completed again).
- `app/Filament/Admin/Resources/BankStatements/Actions/CompleteAllMatchedAction.php` (`:64-66`): wrap **per transaction** inside the `->each(...)` closure so one incomplete purchase order does not abort the loop; collect the described problem strings and send one danger notification listing them after the loop (body: `implode(PHP_EOL, $lines)`; when `$lines === []` send nothing extra — the success notification still fires).
- `app/Filament/Admin/Resources/BankTransactions/Actions/UnlinkReversalAction.php` (`:28-30`): wrap `$service->unlinkReversal(...)` in the same try/catch + danger notification (`unlinkReversal` re-marks purchase orders as pending).

Add a small helper in `PurchaseOrderProblemLabels` (see below) used by all four call sites.

## Language files

`app/Filament/Admin/Labels/PurchaseOrderProblemLabels.php` (next to `PurchaseOrderStatusLabels.php`):

```php
final class PurchaseOrderProblemLabels
{
    public static function describe(PurchaseOrderProblem $problem): string
    {
        $label = match ($problem->type) {
            PurchaseOrderProblemType::MissingCostCenter => __('labels.problem_missing_cost_center', ['line' => $problem->lineNumber]),
            PurchaseOrderProblemType::UnknownCostCenter => __('labels.problem_unknown_cost_center', ['line' => $problem->lineNumber]),
            PurchaseOrderProblemType::MissingCreditorName => __('labels.problem_missing_creditor_name'),
            PurchaseOrderProblemType::MissingCreditorIban => __('labels.problem_missing_creditor_iban'),
        };

        return sprintf('#%d: %s', $problem->purchaseOrderId->value, $label);
    }

    /** @param list<PurchaseOrderProblem> $problems */
    public static function describeAll(array $problems): string
    {
        return implode(PHP_EOL, array_map(self::describe(...), $problems));
    }
}
```

`lang/nl/labels.php` — add:

```php
'cost_center_optional_hint' => 'Optioneel zolang de inkooporder open is; verplicht voordat hij als in behandeling wordt gemarkeerd',
'problem_missing_cost_center' => 'Regel :line heeft geen kostenplaats',
'problem_unknown_cost_center' => 'Regel :line verwijst naar een onbekende kostenplaats',
'problem_missing_creditor_name' => 'De crediteursnaam ontbreekt',
'problem_missing_creditor_iban' => 'Het IBAN van de crediteur ontbreekt',
```

`lang/nl/notifications.php` — add:

```php
'purchase_order_incomplete' => 'Inkooporder is niet compleet',
```

## Models and factories

- `app/Models/PurchaseOrderLine.php`: extend the class PHPDoc with `@property ?int $cost_center_id` (nullable now). `#[Fillable]` already includes `cost_center_id`.
- `database/factories/PurchaseOrderLineFactory.php`: add a state for the optional case (default keeps a cost center so existing tests stay valid):

```php
public function withoutCostCenter(): self
{
    return $this->state(['cost_center_id' => null]);
}
```

- `database/factories/PurchaseOrderFactory.php`: add a state for gate tests:

```php
public function withoutCreditor(): self
{
    return $this->state(['creditor_name' => null, 'creditor_iban' => null]);
}
```

## Tests

`tests/Unit/Domain/PurchaseOrders/PurchaseOrderRepositoryExpectation.php` — add an expectation for the new read port:

```php
/**
 * @param list<PurchaseOrderCompleteness> $return
 */
public function expectsGetCompleteness(PurchaseOrderIdList $ids, array $return): void
{
    $this->mock
        ->expects('getCompleteness')
        ->with(equalTo($ids))
        ->andReturn($return);
}
```

`tests/Unit/Domain/PurchaseOrders/PurchaseOrderServiceImplTest.php` — update the two happy-path tests (`markAsPending`, `markAsPaid`) to stub `expectsGetCompleteness(...)` returning a complete purchase order first, and add gate tests (assert the exception and that `markAsPending`/`createForPurchaseOrder` are **never** called — use `shouldNotReceive` on the mocks):

- `test_mark_as_pending_fails_when_a_line_has_no_cost_center` — line entry with `costCenterId: null` → `PurchaseOrderIncompleteException`, problem type `MissingCostCenter`, `lineNumber: 1`.
- `test_mark_as_pending_fails_when_a_line_references_an_unknown_cost_center` — `costCenterId: 7, costCenterExists: false` → `UnknownCostCenter`.
- `test_mark_as_pending_fails_when_creditor_name_is_missing` / `..._creditor_iban_is_missing` → `MissingCreditorName` / `MissingCreditorIban`.
- `test_mark_as_pending_collects_problems_for_all_lines_and_purchase_orders` — multiple lines/POs → assert `$exception->problems` contains all entries.
- `test_mark_as_paid_applies_the_same_gate` — incomplete entry → exception, `markAsPaid` never called.
- `test_mark_as_declined_is_not_gated` — already covered by existing tests; keep them green without `getCompleteness` stubs.

`tests/Feature/Filament/Admin/Resources/PurchaseOrderResourceTest.php`:

- Existing creation tests (`test_can_create_purchase_order_with_lines`, `test_assigning_member_fills_creditor_name_and_iban`, `test_create_page_prefills_creditor_data_from_member_query_parameter`) now fail without an image: add `'image_path' => UploadedFile::fake()->image('factuur.jpg')` to `fillForm([...])` (import `Illuminate\Http\UploadedFile`).
- `test_can_create_purchase_order_without_cost_center` — line with `'cost_center_id' => null` → `assertHasNoFormErrors()` + `assertDatabaseHas('purchase_order_lines', [..., 'cost_center_id' => null])`.
- `test_image_is_required_when_creating_a_purchase_order` — omit `image_path` → `assertHasFormErrors(['image_path'])`.
- `test_mark_as_pending_is_blocked_when_a_line_has_no_cost_center` — `PurchaseOrder::factory()->open()->create()` + `PurchaseOrderLine::factory()->withoutCostCenter()->create([...])` → `callAction('markAsPending')`, then assert status still `Open`, no `bookkeeping_records`, and `assertNotified()` with the danger notification.
- `test_mark_as_pending_is_blocked_when_the_cost_center_does_not_exist` — insert a line with a bogus FK inside `Schema::withoutForeignKeyConstraints(...)` (tests run sqlite with FKs on) → still `Open`, notified.
- `test_mark_as_pending_is_blocked_without_creditor_information` — `PurchaseOrder::factory()->open()->withoutCreditor()->create()` with a complete line → still `Open`, notified.
- `test_mark_as_pending_succeeds_when_complete` — already covered by `test_mark_as_pending_creates_bookkeeping_records`; keep green (factory supplies creditor + line cost center).
- `test_mark_as_paid_is_blocked_when_incomplete` — open PO with `withoutCostCenter()` line, `callAction('markAsPaid')` is hidden on `Open` so use `app(PurchaseOrderService::class)->markAsPaid(...)` and `expectException`/`try-catch` + assert status unchanged and no bookkeeping rows.

`tests/Feature/Filament/BankTransaction/BankTransactionResourceTest.php`:

- `createPurchaseOrderFromTransaction` test (`:129`) must now pass `'image_path' => UploadedFile::fake()->image('bonnetje.jpg')`; extend its assertions with `'image_path'` persisted on the created purchase order (covers the previously dropped field).
- Optional hardening test: completing a transaction attached to an incomplete purchase order leaves the transaction open and shows the failure notification.

Run the affected tests only, e.g. `./Taskfile artisan test --compact tests/Unit/Domain/PurchaseOrders/PurchaseOrderServiceImplTest.php tests/Feature/Filament/Admin/Resources/PurchaseOrderResourceTest.php tests/Feature/Filament/BankTransaction/BankTransactionResourceTest.php`, then ask the user about running the full suite.

## Documentation updates

`.agents/docs/purchase-orders/CONTEXT.md` — update the PurchaseOrder / PurchaseOrderLine / PurchaseOrderStatus entries: the image is now required on creation; a line's CostCenter is optional while `Open` but mandatory before `Pending`; creditor details are optional while `Open` but mandatory before `Pending`; `Pending`/`Paid` transitions validate completeness (every line has an existing CostCenter, creditor name + IBAN filled) and throw `PurchaseOrderIncompleteException` otherwise. Also adjust `.agents/docs/CONTEXT-MAP.md` line "PurchaseOrderLines carry a CostCenter reference" to "carry an optional CostCenter reference (mandatory before payment)".

## Notes and edge cases

- **Why the gate also guards `markAsPaid`**: `BankTransactionServiceImpl::complete()` sends purchase orders straight to `Paid` regardless of their current status, and bookkeeping records (which need `cost_center_id`) are created on that transition too. The guard is a no-op for purchase orders that passed the `Pending` gate. Validation runs before any write, so a blocked transition leaves everything untouched.
- **Locked creditor fields**: when `member_id` is set, the creditor inputs are disabled (`PurchaseOrderForm.php:97-103`). A member without `payment_information.banking_account_number` prefills an empty `creditor_iban`, which the gate would block. Clearing the member select unlocks the fields for manual entry — no dead end. Do not change this behaviour.
- **Image scope**: "required" is enforced at form level (both creation forms). It is intentionally **not** part of the pending/paid gate — legacy `Open` purchase orders without an image can still be marked pending. `purchase_orders.image_path` stays nullable in the DB (legacy rows, `PurchaseOrderObserverTest` relies on `image_path => null`).
- **`createForPurchaseOrder`** (`BookkeepingRecordDbRepository.php:66-103`) needs no change: after the gate, every line of a `Pending`/`Paid` purchase order has a real cost center. A purchase order with zero lines passes the gate (vacuously complete) and simply creates no bookkeeping rows — accepted, not part of this change.
- **Pre-existing, out of scope**: `BankTransactionServiceImpl::complete()` updates invoices and purchase orders without a wrapping DB transaction — a gate failure mid-flow can leave attached invoices `Paid` while the transaction stays open. Worth a follow-up (wrap `complete()` in `DB::transaction`), but not part of this change.
- Keep everything behind `./Taskfile` commands, follow the PHPUnit-only test rule (no Pest), and run the minimal test set before handing back.
