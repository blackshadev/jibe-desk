# Create Invoice, Purchase Order, and Bookkeeping Records from Banking Transaction View

**Date:** 2026-07-20  
**Goal:** Allow users to easily create new Invoices, Purchase Orders, and Bookkeeping records directly from the banking transaction view, with fields pre-filled from the banking transaction (amount, IBAN, account name, description).

---

## Overview

Currently the banking transaction view (ViewBankingTransaction) has RelationManagers for Invoices, PurchaseOrders, and BookkeepingRecords that only allow **attaching existing** records. This plan adds "Create & Attach" actions that open a modal form pre-filled with data from the banking transaction, create the record, and automatically link it.

---

## Architecture Decisions

### 1. Modal-based creation (not page navigation)
- **BookkeepingRecord** form is simple (4 fields), fits well in a modal.
- **Invoice** and **PurchaseOrder** forms are moderately complex, but a modal with essential fields + one line is acceptable. Users can edit later to add more lines.
- Keeping the user in-context on the banking transaction view is better UX than navigating away.

### 2. Auto-attach after creation
- After creating the record, immediately link it to the banking transaction via the existing `BankTransactionRepository` methods (`attachInvoice`, `attachPurchaseOrder`, `attachBookkeepingRecord`).

### 3. Amount handling
- Banking transaction `amount` is signed: positive = credit (incoming), negative = debit (outgoing).
- For **Invoice** creation: use the amount as-is (typically positive for income).
- For **PurchaseOrder** creation: use `-$bt->amount` since PO line prices are always positive.
- For **BookkeepingRecord** creation: use the amount as-is for the price input.

### 4. Visibility
- All new actions are hidden when the banking transaction `isCompleted()` (same pattern as existing Attach actions).

### 5. Code organization
- Each action is a standalone class in `app/Filament/Admin/Resources/BankingTransactions/Actions/`.
- Follows the exact same pattern as existing `AttachInvoiceAction`, `AttachPurchaseOrderAction`, and `AttachBookkeepingRecordAction`.

---

## Files to Create

### 1. `app/Filament/Admin/Resources/BankingTransactions/Actions/CreateInvoiceFromTransactionAction.php`

A new Action class that opens a modal to create an Invoice, pre-filled from the banking transaction, then attaches it.

**Form fields (in modal):**

| Field | Pre-filled from BT                     | Notes |
|---|----------------------------------------|---|
| `member_id` | (none, user selects)                   | Searchable Select, required. After selection, auto-fills recipient fields (same pattern as `InvoiceForm`). |
| `date` | `$bt->date`                            | DatePicker, required, hidden (auto-set). |
| `line_description` | `$bt->description` (truncated if needed) | TextInput for the first invoice line description. |
| `line_price` | `$bt->amount`                          | TextInput for the first invoice line price. |
| `line_quantity` | `1`                                    | TextInput, default 1. |
| `cost_center_id` | (none, user selects)                   | Select, required. |

**Implementation details:**
- Use `Action::make()` with `->schema([...])` and `->action(...)`.
- `->visible(static fn (RelationManager $livewire): bool => !$livewire->getOwnerRecord()->isCompleted())`.
- In the `action` callback:
  1. Inject `InvoiceNumberGenerator` to generate the invoice number.
  2. Create the `Invoice` model with: `member_id`, `date`, `status=InvoiceStatus::Open`, `invoice_number`, and recipient fields auto-filled from the member.
  3. Set `invoice_number` = `InvoiceNumberGenerator->generate()->value` (see `CreateInvoice::mutateFormDataBeforeCreate`).
  4. Create one `InvoiceLine` with: `description`, `price`, `vat=price*0.21`, `quantity`, `cost_center_id` (same as `InvoiceForm::mutateRelationshipDataBeforeCreateUsing`).
  5. Call `BankTransactionService->attachInvoice(BankTransactionId::create($bt->id), InvoiceId::create($invoice->id))`.
  6. Refresh the RelationManager table.
- `->successNotificationTitle(__('notifications.invoice_created'))` or custom notification with attachment info.

**Member auto-fill logic (inside `afterStateUpdated` on member_id Select):**
```php
->afterStateUpdated(function (?int $state, Set $set) {
    if ($state === null) return;
    $member = Member::findOrFail($state);
    $set('recipient_name', $member->name);
    $set('recipient_address', $member->address);
    $set('recipient_email', $member->email);
})
```
Note: recipient fields (`recipient_name`, `recipient_address`, `recipient_email`) need to be hidden fields in the form (they are set programmatically, not user-editable in this modal).

---

### 2. `app/Filament/Admin/Resources/BankingTransactions/Actions/CreatePurchaseOrderFromTransactionAction.php`

A new Action class that opens a modal to create a PurchaseOrder, pre-filled from the banking transaction, then attaches it.

**Form fields (in modal):**

| Field | Pre-filled from BT           | Notes |
|---|------------------------------|---|
| `date` | `$bt->date`                  | DatePicker, required. |
| `description` | `$bt->description`           | TextInput, required. |
| `creditor_iban` | `$bt->banking_account_number` | TextInput with Iban validation rule. |
| `creditor_name` | (empty, user fills)          | TextInput. Could also attempt to parse from BT description if a pattern exists. |
| `line_description` | `$bt->description`           | TextInput for the first PO line. |
| `line_price` | `-$bt->amount`              | TextInput for the first PO line price. |
| `line_price_vat` | `line_price * 0.21`          | TextInput, auto-calculated from line_price. |
| `cost_center_id` | (none, user selects)         | Select, required. |

**Implementation details:**
- Use `Action::make()` with `->schema([...])` and `->action(...)`.
- `->visible(static fn (RelationManager $livewire): bool => !$livewire->getOwnerRecord()->isCompleted())`.
- In the `action` callback:
  1. Create the `PurchaseOrder` model with: `date`, `description`, `creditor_iban`, `creditor_name`, `status=PurchaseOrderStatus::Open`.
  2. Create one `PurchaseOrderLine` with: `description`, `price`, `price_vat`, `cost_center_id`.
  3. Call `BankTransactionService->attachPurchaseOrder(BankTransactionId::create($bt->id), PurchaseOrderId::create($po->id))`.
  4. Refresh the RelationManager table.
- Auto-calculate `price_vat` from `price` using `afterStateUpdated` (same pattern as existing `PurchaseOrderForm`):
```php
->afterStateUpdated(function (?float $state, Get $get, Set $set) {
    if ($state === null) return;
    $set('price_vat', round($state * 0.21, 2));
})
```

---

### 3. `app/Filament/Admin/Resources/BankingTransactions/Actions/CreateBookkeepingRecordFromTransactionAction.php`

A new Action class that opens a modal to create a BookkeepingRecord, pre-filled from the banking transaction, then attaches it.

**Form fields (in modal):**

| Field | Pre-filled from BT | Notes |
|---|---|---|
| `year` | `now()->year` | TextInput, numeric, required. |
| `cost_center_id` | (none, user selects) | Select (relationship `costCenter`), required. |
| `description` | `$bt->description` | TextInput, required. |
| `amount` | `abs($bt->amount)` formatted as signless string | TextInput with `€` prefix. Uses `CompoundPrice` hydrator/dehydrator pattern matching `BookkeepingRecordForm`. |

**Implementation details:**
- Use `Action::make()` with `->schema([...])` and `->action(...)`.
- `->visible(static fn (RelationManager $livewire): bool => !$livewire->getOwnerRecord()->isCompleted())`.
- In the `action` callback:
  1. Create the `BookkeepingRecord` model with: `year`, `cost_center_id`, `description`, `amount_price` and `amount_vat` (extract from CompoundPrice after dehydrate). Since this bookkeeping record represents a direct bank transaction (not linked to an invoice/PO), use `CompoundPrice::create(price)` which sets `vat = price * 0.21`.
  2. Call `BankTransactionRepository->attachBookkeepingRecord(BankTransactionId::create($bt->id), $record->id)`.
  3. Refresh the RelationManager table.
- The `amount` field needs special handling because `BookkeepingRecord` uses a `CompoundPrice` accessor/mutator:
  - Display: Use `PriceFormatter::formatSignless()`.
  - Dehydrate: Use `CompoundPrice::create(PriceFormatter::parse($state))`.
  - Regex validation: `'/^\d+((\.|,)\d{0,3})?$/'` (same as `BookkeepingRecordForm`).

---

## Files to Modify

### 4. `app/Filament/Admin/Resources/BankingTransactions/RelationManagers/InvoicesRelationManager.php`

**Change:** Add `CreateInvoiceFromTransactionAction::make()` to the `headerActions` array, next to the existing `AttachInvoiceAction::make()`.

**Current code (line 46-48):**
```php
headerActions(
    [AttachInvoiceAction::make()],
)
```

**New code:**
```php
headerActions(
    [
        AttachInvoiceAction::make(),
        CreateInvoiceFromTransactionAction::make(),
    ],
)
```

Also add the import:
```php
use App\Filament\Admin\Resources\BankingTransactions\Actions\CreateInvoiceFromTransactionAction;
```

---

### 5. `app/Filament/Admin/Resources/BankingTransactions/RelationManagers/PurchaseOrdersRelationManager.php`

**Change:** Add `CreatePurchaseOrderFromTransactionAction::make()` to the `headerActions` array, next to the existing `AttachPurchaseOrderAction::make()`.

**Current code (line 44-46):**
```php
headerActions(
    [AttachPurchaseOrderAction::make()],
)
```

**New code:**
```php
headerActions(
    [
        AttachPurchaseOrderAction::make(),
        CreatePurchaseOrderFromTransactionAction::make(),
    ],
)
```

Also add the import:
```php
use App\Filament\Admin\Resources\BankingTransactions\Actions\CreatePurchaseOrderFromTransactionAction;
```

---

### 6. `app/Filament/Admin/Resources/BankingTransactions/RelationManagers/BookkeepingRecordsRelationManager.php`

**Change:** Add `CreateBookkeepingRecordFromTransactionAction::make()` to the `headerActions` array, next to the existing `AttachBookkeepingRecordAction::make()`.

**Current code (line 40-42):**
```php
headerActions(
    [AttachBookkeepingRecordAction::make()],
)
```

**New code:**
```php
headerActions(
    [
        AttachBookkeepingRecordAction::make(),
        CreateBookkeepingRecordFromTransactionAction::make(),
    ],
)
```

Also add the import:
```php
use App\Filament\Admin\Resources\BankingTransactions\Actions\CreateBookkeepingRecordFromTransactionAction;
```

---

### 7. `lang/nl/labels.php`

Add the following labels at the end of the array (before the closing `]`):

```php
'create_invoice_from_transaction' => 'Factuur aanmaken',
'create_purchase_order_from_transaction' => 'Inkooporder aanmaken',
'create_bookkeeping_record_from_transaction' => 'Boekhouding mutatie aanmaken',
```

### 8. `lang/nl/notifications.php`

Add the following notification labels at the end of the array (before the closing `]`):

```php
'invoice_created_and_attached' => 'Factuur aangemaakt en gekoppeld',
'purchase_order_created_and_attached' => 'Inkooporder aangemaakt en gekoppeld',
'bookkeeping_record_created_and_attached' => 'Boekhouding mutatie aangemaakt en gekoppeld',
```

---

## Testing Plan

### Test file: `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php`

Add these test methods to the existing test class:

#### 1. `test_can_create_invoice_from_transaction`
- Create a `BankingTransaction` (open, amount=150.00, description="Test payment")
- Create a `Member` with factory
- Create a `CostCenter` with factory
- Test the `InvoicesRelationManager` via the View page:
  - Mount the action `createInvoiceFromTransaction`
  - Verify fields are pre-filled: `date` = BT date, `line_description` = BT description
  - Fill `member_id` and `cost_center_id`
  - Call the action
  - Assert: Invoice created with correct fields, InvoiceLine created, attached to BT
  - Assert database has the correct `banking_transaction_references` row

#### 2. `test_create_invoice_action_hidden_when_completed`
- Create a completed `BankingTransaction`
- Verify the `createInvoiceFromTransaction` action is not visible

#### 3. `test_can_create_purchase_order_from_transaction`
- Create a `BankingTransaction` (open, amount=-200.00, banking_account_number="NL91ABNA0417164300")
- Create a `CostCenter`
- Test via View page / RelationManager
- Mount action, verify pre-fill: `date`, `description`, `creditor_iban` from BT
- Fill `cost_center_id`
- Call action
- Assert: PurchaseOrder created, line created (price=200.00, price_vat=42.00), attached to BT

#### 4. `test_can_create_bookkeeping_record_from_transaction`
- Create a `BankingTransaction` (open, amount=-50.00, description="Office supplies")
- Create a `CostCenter`
- Test via View page / RelationManager
- Mount action, verify pre-fill: `year`=current, `description`=BT, `amount`=50.00
- Fill `cost_center_id`
- Call action
- Assert: BookkeepingRecord created with `banking_transaction_id` set

#### 5. `test_bookkeeping_record_amount_negative_handling`
- Verify that `abs()` is used for the pre-filled amount when BT amount is negative.

#### 6. `test_actions_hidden_when_transaction_completed`
- Create a completed `BankingTransaction`
- Verify all three "create" actions are not visible in their respective RelationManagers.

---

## Data Flow Summary

```
BankingTransaction (View page)
│
├── InvoicesRelationManager
│   ├── [existing] AttachInvoiceAction (modal: select existing invoice)
│   └── [NEW] CreateInvoiceFromTransactionAction (modal: create new invoice + attach)
│       Pre-fill: date ← BT.date, line_description ← BT.description, line_price ← abs(BT.amount)
│
├── PurchaseOrdersRelationManager
│   ├── [existing] AttachPurchaseOrderAction (modal: select existing PO)
│   └── [NEW] CreatePurchaseOrderFromTransactionAction (modal: create new PO + attach)
│       Pre-fill: date ← BT.date, description ← BT.description,
│                 creditor_iban ← BT.banking_account_number, line_price ← abs(BT.amount)
│
└── BookkeepingRecordsRelationManager
    ├── [existing] AttachBookkeepingRecordAction (modal: select existing record)
    └── [NEW] CreateBookkeepingRecordFromTransactionAction (modal: create new record + attach)
        Pre-fill: year ← now()->year, description ← BT.description, amount ← abs(BT.amount)
```

---

## Key Considerations

1. **Transaction safety:** Each action uses Eloquent model creation within the action callback. No explicit transaction wrapping needed since we create one model + one line and attach — if attachment fails, the record still exists (which is acceptable — user can manually attach later).

2. **Invoice number generation:** Use `app(InvoiceNumberGenerator::class)->generate()->value` (same as `CreateInvoice::mutateFormDataBeforeCreate`). Do NOT use `InvoiceRepository->create()` domain method, because that requires a full `NewInvoice` DTO with `BillableItemList`. Instead, create the Eloquent model directly (matching how `CreateInvoice` Filament page works — it uses `CreateRecord` which calls `$this->getModel()::create($data)`).

3. **CompoundPrice handling for BookkeepingRecord:** The `BookkeepingRecord` model has a custom `amount` accessor/mutator that reads/writes `CompoundPrice`. The form field must:
   - Format display: `PriceFormatter::formatCompoundSignless($record?->amount)`
   - Dehydrate: `CompoundPrice::create(PriceFormatter::parse($state))`
   - This ensures `amount_price` and `amount_vat` columns are populated correctly.

4. **Member auto-fill for Invoice:** After selecting a member, the `afterStateUpdated` callback on the Select component automatically fills hidden `recipient_name`, `recipient_address`, and `recipient_email` fields. These fields must be included as hidden form fields in the schema (not visible to user but set programmatically).

5. **VAT auto-calculation:** For invoice lines: `vat = price * 0.21` (matching `InvoiceForm::mutateRelationshipDataBeforeCreateUsing`). For purchase order lines: `price_vat = round(price * 0.21, 2)` (matching `PurchaseOrderForm`). For bookkeeping records: VAT is handled by `CompoundPrice::create()`.

6. **Refresh after creation:** Call `$livewire->dispatch('refresh')` or use the RelationManager's `$this->resetTable()` / `$livewire->refresh()` pattern to update the table after attachment. Look at how `CompleteBankingTransactionAction` uses `$page->dispatch('refresh')` — for RelationManagers, use `$livewire->dispatch('refresh')` on the owner Livewire component to refresh the relation managers.

7. **Dependency injection in action callbacks:** The `->action()` closure receives `$data`, the `RelationManager $livewire`, and can type-hint additional dependencies (like `BankTransactionService`, `InvoiceNumberGenerator`) — Laravel's container resolves them automatically.
