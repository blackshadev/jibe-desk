# Credit Invoices

**Date**: 2026-08-01
**Status**: Ready for implementation

## Overview

Ability to create a credit invoice from an existing invoice (pending, paid, or declined). The credit negates the original invoice amounts and is linked back to the original via a self-referencing foreign key. Credit invoices start in `Open` status.

## Current State Analysis

### Invoice Status Lifecycle
```
Open ──► Pending ──► Paid
              │
              └──► Declined
```
- **Open**: Editable, can be added to batches. Created as the result of creating a credit.
- **Pending**: Part of a closed batch (locked, SEPA exported, emails queued). A credit can be created from this state.
- **Paid**: Payment received via bank transaction matching/completion. A credit can be created from this state.
- **Declined**: Refused. A credit can be created from this state.

### Existing Related Code

| File | Purpose |
|------|---------|
| `app/Models/Invoice.php` | Eloquent model with `member()`, `invoiceBatch()`, `lines()`, `bankingTransactions()` relations |
| `app/Domain/Invoices/InvoiceStatus.php` | Enum: Open, Pending, Paid, Declined |
| `app/Domain/Invoices/InvoiceService.php` | Interface: currently only has `markAsPaid(InvoiceIdList)` |
| `app/Domain/Invoices/InvoiceServiceImpl.php` | Implementation |
| `app/Domain/Invoices/InvoiceNumberGenerator.php` / `InvoiceNumberGeneratorImpl.php` | Generates `I-YYYY######` invoice numbers |
| `app/Domain/Invoices/CompoundPrice.php` | Price + VAT value object |
| `app/Domain/Invoices/InvoiceId.php` | ID value object (extends NumericId) |
| `app/Policies/InvoicePolicy.php` | Only allows update/delete when status == Open |
| `app/Filament/Admin/Resources/Invoices/InvoiceResource.php` | Filament resource |
| `app/Filament/Admin/Resources/Invoices/Pages/EditInvoice.php` | Edit page |
| `app/Filament/Admin/Resources/Invoices/Pages/ViewInvoice.php` | View page |
| `app/Filament/Admin/Resources/Invoices/Tables/InvoicesTable.php` | Table config |
| `app/Filament/Admin/Resources/Invoices/Schemas/InvoiceForm.php` | Form schema |
| `app/Infrastructure/Invoices/SepaExportServiceImpl.php` | Already handles negative amounts as credit transfers in SEPA XML |
| `lang/nl/labels.php` | All Dutch labels |
| `lang/nl/notifications.php` | Notification messages |
| `database/migrations/2026_05_15_073548_create_invoices_table.php` | Base invoices table |
| `database/migrations/2026_05_31_130930_add_status_to_invoices.php` | Added `status` column |

### How the System Already Handles "Credits"

The term "credit" currently appears in two contexts:
1. **SEPA credit transfers**: The `SepaExportServiceImpl` generates credit transfer XML for invoices with negative amounts (money flowing from the club to the member). This is the closest existing concept.
2. **Bank transaction matching**: `InvoiceRepository.findMatchingCredit()` matches incoming bank transaction credits to invoices.

However, there is **no dedicated credit note entity or credit relationship between invoices**. This feature adds it as credit relationship between invoices.

---

## Requirements

1. From an invoice with status `Pending`, `Paid`, or `Declined`, create a new credit invoice
2. The credit invoice is linked to the original invoice via a `credit_invoice_id` foreign key
3. The credit invoice starts in `Open` status
4. The credit invoice has negative amounts for all lines (negating the original)
5. Same member, same recipient info, same cost centers
6. New invoice number (generated fresh)
7. Current date

---

## Implementation Plan

### Step 1: Database Migration

**File**: `database/migrations/2026_08_01_000000_add_credit_invoice_id_to_invoices.php`

Add `credit_invoice_id` column to the `invoices` table:
```php
$table->foreignId('credit_invoice_id')
    ->nullable()
    ->constrained('invoices')
    ->nullOnDelete();
```

This creates a self-referencing FK. An invoice that has `credit_invoice_id` set is a credit of the referenced invoice. The referenced invoice may have multiple credit invoices (one-to-many).

### Step 2: Update Invoice Model

**File**: `app/Models/Invoice.php`

Add relationships:
```php
/** @return BelongsTo<Invoice, $this> */
public function creditInvoice(): BelongsTo
{
    return $this->belongsTo(Invoice::class, 'credit_invoice_id');
}

/** @return HasOne<Invoice, $this> */
public function creditInvoices(): HasOne
{
    return $this->hasOne(Invoice::class, 'credit_invoice_id');
}
```

### Step 3: Add Domain Service Method

**File**: `app/Domain/Invoices/InvoiceService.php`

Add method:
```php
public function createCredit(InvoiceId $originalInvoiceId): InvoiceId;
```

**File**: `app/Domain/Invoices/InvoiceServiceImpl.php`

Since creating a credit is specific and differs from the normal `create(NewInvoice)` flow (which uses BillableItemList), the implementation uses the Eloquent model directly in a transaction:

1. Load the original invoice with its lines
2. Map each line to a new set with negative price and negative vat (quantity stays the same)
3. Create the credit invoice in a DB transaction
4. Set `credit_invoice_id` on the new invoice
5. Return the new InvoiceId

```php
#[Override]
public function createCredit(InvoiceId $originalInvoiceId): InvoiceId
{
    $original = Invoice::with('lines')->findOrFail($originalInvoiceId->value);
    $invoiceNumber = $this->invoiceNumberGenerator->generate();
    
    $credit = DB::transaction(function () use ($original) {
        $creditInvoice = Invoice::query()->create([
            'member_id' => $original->member_id,
            'credit_invoice_id' => $original->id,
            'recipient_email' => $original->recipient_email,
            'recipient_name' => $original->recipient_name,
            'recipient_address' => $original->recipient_address,
            'invoice_number' => $invoiceNumber->value,
            'date' => now(),
            'status' => InvoiceStatus::Open,
        ]);
        
        foreach ($original->lines as $line) {
            $creditInvoice->lines()->create([
                'description' => $line->description,
                'price' => -$line->price,
                'vat' => -$line->vat,
                'quantity' => $line->quantity,
                'billable_item_id' => $line->billable_item_id,
                'cost_center_id' => $line->cost_center_id,
            ]);
        }
        
        return $creditInvoice;
    });
    
    return InvoiceId::create($credit->id);
}
```

Add dependency: `InvoiceNumberGenerator` to `InvoiceServiceImpl` constructor (may already be available via `InvoiceRepository` — check and add if needed).

### Step 4: Add Filament Actions

**File**: `app/Filament/Admin/Resources/Invoices/Pages/EditInvoice.php`

Add header action:
```php
Action::make('createCredit')
    ->label(__('labels.create_credit_invoice'))
    ->icon('heroicon-m-arrow-uturn-left')
    ->color('warning')
    ->requiresConfirmation()
    ->modalDescription(__('labels.create_credit_invoice_warning'))
    ->visible(static fn (Invoice $record) => auth()->user()->can('create_credit', $record))
    ->action(function (Invoice $record, InvoiceService $invoiceService) {
        $invoiceService->createCredit(InvoiceId::create($record->id));
    })
    ->successNotificationTitle(__('notifications.credit_invoice_created')),
```

**File**: `app/Filament/Admin/Resources/Invoices/Pages/ViewInvoice.php`

Same action as EditInvoice.

### Step 5: Show Credit Reference on Invoice Form

**File**: `app/Filament/Admin/Resources/Invoices/Schemas/InvoiceForm.php`

When viewing/editing a credit invoice, show a read-only reference to the original invoice:
- If `credit_invoice_id` is set, display the original invoice number as a link

### Step 6: Update InvoicePolicy

**File**: `app/Policies/InvoicePolicy.php`

Add custom policy method for the credit action:
```php
public function createCredit(User $user, Model $invoice): bool
{
    Assert::isInstanceOf($invoice, Invoice::class);
    return $user->can('create_invoices')
        && in_array($invoice->status, [InvoiceStatus::Pending, InvoiceStatus::Paid, InvoiceStatus::Declined], true);
}
```

Uses the existing `create_invoices` permission (no new permission needed).

### Step 7: Update Language Files

**File**: `lang/nl/labels.php`

Add:
```php
'create_credit_invoice' => 'Maak creditfactuur',
'create_credit_invoice_warning' => 'Weet je zeker dat je een creditfactuur wilt aanmaken? Dit maakt een nieuwe factuur aan met negatieve bedragen die de originele factuur compenseert. De originele factuur blijft bestaan.',
'credit_invoice' => 'Creditfactuur',
'original_invoice' => 'Originele factuur',
```

**File**: `lang/nl/notifications.php`

Add:
```php
'credit_invoice_created' => 'Creditfactuur succesvol aangemaakt',
```

---

## Implementation Order

1. **Database Migration** (`credit_invoice_id` column)
2. **Invoice Model** (add relationships)
3. **Language Files** (add credit labels)
4. **InvoiceService + InvoiceServiceImpl** (add `createCredit()`)
   - `InvoiceServiceImpl` needs `InvoiceNumberGenerator` injected in constructor
5. **InvoicePolicy** (add `createCredit` method)
6. **Filament Pages** (EditInvoice, ViewInvoice — add header action)
7. **InvoicesTable** (add row action)
8. **InvoiceForm** (show credit_invoice_id reference when viewing credit invoices)
9. **Tests**

---

## Testing Plan

### Unit Tests (`tests/Unit/Domain/Invoices/`)

#### InvoiceServiceImplTest (new or update existing)

1. **`test_create_credit_creates_invoice_with_negative_amounts()`**
   - Given an original invoice with lines (positive amounts)
   - When `createCredit(originalInvoiceId)` is called
   - Then a new invoice exists with status=Open, credit_invoice_id=originalInvoiceId
   - And all lines have negative price and negative vat
   - And quantity remains the same

2. **`test_create_credit_copies_member_and_recipient_info()`**
   - Credit invoice has same member_id, recipient_email, recipient_name, recipient_address as original

### Feature Tests (`tests/Feature/Filament/`)

#### InvoiceResourceTest (new file)

1. **`test_credit_action_visible_for_pending_invoice()`**
   - Login as admin, navigate to invoice listing
   - A pending invoice should show the "Create Credit" action
   
2. **`test_credit_action_not_visible_for_open_invoice()`**
   - Open invoice should NOT have the credit action

3. **`test_credit_action_creates_credit_invoice()`**
   - Execute the credit action on a pending invoice
   - Assert a new invoice exists with credit_invoice_id set

### Factory Updates

**File**: `database/factories/InvoiceFactory.php`

Add state:
```php
public function creditOf(Invoice $original): static
{
    return $this->state(fn (array $attributes) => [
        'credit_invoice_id' => $original->id,
    ]);
}
```

---

## Edge Cases & Considerations

- **Credit of a credit**: Creating a credit from an already-credited invoice should be allowed (it creates a credit of the credit, which effectively reverses the credit — resulting in positive amounts again)
- **Credit from paid invoice**: The paid status of the original does not change. The credit is a separate invoice
- **Credit from declined invoice**: Similarly, original stays declined
- **Invoice number**: Always generates a new `I-YYYY######` number, same as any other invoice
- **InvoiceBatch**: Credit invoices are NOT added to any batch automatically. They start as standalone open invoices
- **SEPA export**: Since credit invoices have negative amounts, they'll be included in the credit transfer XML when exported in a future batch. This is handled by existing `SepaExportServiceImpl` which already processes negative amounts as credit transfers

---

## Files Changed Summary

| Action | File |
|--------|------|
| Create | `database/migrations/2026_08_01_000000_add_credit_invoice_id_to_invoices.php` |
| Modify | `app/Models/Invoice.php` |
| Modify | `app/Domain/Invoices/InvoiceService.php` |
| Modify | `app/Domain/Invoices/InvoiceServiceImpl.php` |
| Modify | `app/Policies/InvoicePolicy.php` |
| Modify | `app/Filament/Admin/Resources/Invoices/Pages/EditInvoice.php` |
| Modify | `app/Filament/Admin/Resources/Invoices/Pages/ViewInvoice.php` |
| Modify | `app/Filament/Admin/Resources/Invoices/Tables/InvoicesTable.php` |
| Modify | `app/Filament/Admin/Resources/Invoices/Schemas/InvoiceForm.php` (optional, show credit ref) |
| Modify | `lang/nl/labels.php` |
| Modify | `lang/nl/notifications.php` |
| Modify | `database/factories/InvoiceFactory.php` |
| Create | `tests/Unit/Domain/Invoices/InvoiceServiceImplTest.php` (or update existing) |
| Create | `tests/Feature/Filament/InvoiceResourceTest.php` |

---

## References

- InvoiceService pattern: `app/Domain/Invoices/InvoiceServiceImpl.php` — injection of `InvoiceRepository` and `BookkeepingRecordRepository`
- InvoicePolicy: `app/Policies/InvoicePolicy.php` — status-based authorization pattern
- InvoiceNumberGenerator: `app/Domain/Invoices/InvoiceNumberGenerator.php` — injectable service for generating numbers
- CompoundPrice for negative amounts: `app/Domain/Invoices/CompoundPrice.php` — can use `CompoundPrice::create()` with negative price
- SepaExportServiceImpl: `app/Infrastructure/Invoices/SepaExportServiceImpl.php` — already handles negative amounts as credit transfers
