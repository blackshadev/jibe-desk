# Manual Invoice Status Changes (Paid / Declined)

**Date**: 2026-08-01
**Status**: Ready for implementation

## Overview

Ability to manually set invoices to `Paid` or `Declined` from `Pending` state, with a confirmation warning about bypassing the normal flow (banking transaction import/matching). This includes the InvoiceResource pages (Edit/View/List) and the InvoiceBatch relation manager.

## Current State Analysis

### Invoice Status Lifecycle
```
Open ──► Pending ──► Paid
              │
              └──► Declined
```
- **Open**: Editable, can be added to batches. Cannot be manually marked as Paid or Declined — must go through the batch flow.
- **Pending**: Part of a closed batch (locked, SEPA exported, emails queued). Can be manually overridden to Paid or Declined (with confirmation warning).
- **Paid**: Payment received via bank transaction matching/completion. Terminal state — cannot be changed.
- **Declined**: Refused. Terminal state — cannot be changed.

### Existing Related Code

| File | Purpose |
|------|---------|
| `app/Domain/Invoices/InvoiceStatus.php` | Enum: Open, Pending, Paid, Declined |
| `app/Domain/Invoices/InvoiceService.php` | Interface: `markAsPaid(InvoiceIdList)` only |
| `app/Domain/Invoices/InvoiceServiceImpl.php` | Implementation: marks paid + creates bookkeeping records |
| `app/Domain/Invoices/InvoiceId.php` | ID value object (extends NumericId) |
| `app/Domain/Invoices/InvoiceIdList.php` | List of InvoiceId |
| `app/Domain/Bookkeeping/BookkeepingRecordRepository.php` | `createForInvoice(InvoiceIdList)` |
| `app/Policies/InvoicePolicy.php` | Only allows update/delete when status == Open |
| `app/Filament/Admin/Resources/Invoices/InvoiceResource.php` | Filament resource |
| `app/Filament/Admin/Resources/Invoices/Pages/ListInvoices.php` | List with tabs (All, Open, Pending, Paid, Declined) |
| `app/Filament/Admin/Resources/Invoices/Pages/EditInvoice.php` | Edit page (only delete action currently) |
| `app/Filament/Admin/Resources/Invoices/Pages/ViewInvoice.php` | View page (no actions currently) |
| `app/Filament/Admin/Resources/Invoices/Tables/InvoicesTable.php` | Table config: only DeleteBulkAction currently |
| `app/Filament/Admin/Resources/InvoiceBatches/RelationManagers/InvoiceBatchInvoicesRelationManager.php` | Has existing `markAsPaid`/`markAsDeclined` actions (batch context only) |
| `app/Filament/Admin/Resources/InvoiceBatches/Helpers/OnPendingInvoice.php` | Visibility helper: pending invoice in pending batch |
| `lang/nl/labels.php` | All Dutch labels |
| `lang/nl/notifications.php` | Notifications: `invoice_status_updated` exists already |

### Existing markAsPaid/markAsDeclined Behavior (Batch Context)

In `InvoiceBatchInvoicesRelationManager` (batch relation manager):
- **markAsPaid**: Visible only when invoice is `Pending` AND batch is `Pending` (via `OnPendingInvoice` helper). Direct DB update: `$record->update(['status' => InvoiceStatus::Paid])`. Does NOT create bookkeeping records (bookkeeping was already done during batch close).
- **markAsDeclined**: Same visibility. Direct DB update: `$record->update(['status' => InvoiceStatus::Declined])`.

These actions bypass the `InvoiceService` layer because they operate within a batch where bookkeeping has already been handled.

**These actions need updating** (see Step 3 below):
- Add confirmation warnings about bypassing the normal flow (via bank transaction import)
- Use the `InvoiceService` methods instead of direct DB updates for consistency

---

## Requirements

1. Manually set invoices to `Paid` from `Pending` state only
2. Manually set invoices to `Declined` from `Pending` state only
3. Both require confirmation with a warning message about bypassing the normal flow
4. Normal flow: invoices should be paid via bank statement import (matching/completing banking transactions) and declined through the batch flow
5. When marking as `Paid`, bookkeeping records must be created (use `InvoiceService.markAsPaid()`)

---

## Implementation Plan

### Step 1: Add Service Method

**File**: `app/Domain/Invoices/InvoiceService.php`

Add method:
```php
public function markAsDeclined(InvoiceIdList $ids): void;
```

**File**: `app/Domain/Invoices/InvoiceServiceImpl.php`

Implement:
```php
#[Override]
public function markAsDeclined(InvoiceIdList $ids): void
{
    Invoice::query()
        ->whereIn('id', array_map(static fn (InvoiceId $id) => $id->value, $ids->ids))
        ->where('status', InvoiceStatus::Pending)
        ->update(['status' => InvoiceStatus::Declined]);
}
```

### Step 2: Add Filament Actions

**Locations where actions are added:**

#### A. On InvoiceResource Pages

**File**: `app/Filament/Admin/Resources/Invoices/Pages/EditInvoice.php`

Add two header actions:
```php
Action::make('markAsPaid')
    ->label(__('labels.mark_as_paid'))
    ->icon('heroicon-m-banknotes')
    ->color('success')
    ->requiresConfirmation()
    ->modalDescription(__('labels.manual_mark_paid_warning'))
    ->modalIcon('heroicon-m-exclamation-triangle')
    ->modalIconColor('danger')
    ->visible(static fn (Invoice $record) => $record->status === InvoiceStatus::Pending)
    ->action(function (Invoice $record, InvoiceService $invoiceService) {
        $invoiceService->markAsPaid(new InvoiceIdList([InvoiceId::create($record->id)]));
    })
    ->successNotificationTitle(__('notifications.invoice_status_updated')),

Action::make('markAsDeclined')
    ->label(__('labels.mark_as_declined'))
    ->icon('heroicon-m-x-circle')
    ->color('danger')
    ->requiresConfirmation()
    ->modalDescription(__('labels.manual_mark_declined_warning'))
    ->modalIcon('heroicon-m-exclamation-triangle')
    ->modalIconColor('danger')
    ->visible(static fn (Invoice $record) => $record->status === InvoiceStatus::Pending)
    ->action(function (Invoice $record, InvoiceService $invoiceService) {
        $invoiceService->markAsDeclined(new InvoiceIdList([InvoiceId::create($record->id)]));
    })
    ->successNotificationTitle(__('notifications.invoice_status_updated')),
```

**File**: `app/Filament/Admin/Resources/Invoices/Pages/ViewInvoice.php`

Same two actions as EditInvoice.

#### B. On ListInvoices Table Row Actions

**File**: `app/Filament/Admin/Resources/Invoices/Tables/InvoicesTable.php`

Add row actions to the table:
```php
->recordActions([
    Action::make('markAsPaid')
        ->label(__('labels.mark_as_paid'))
        ->icon('heroicon-m-banknotes')
        ->color('success')
        ->requiresConfirmation()
        ->modalDescription(__('labels.manual_mark_paid_warning'))
        ->visible(static fn (Invoice $record) => $record->status === InvoiceStatus::Pending)
        ->action(function (Invoice $record, InvoiceService $invoiceService) {
            $invoiceService->markAsPaid(new InvoiceIdList([InvoiceId::create($record->id)]));
        })
        ->successNotificationTitle(__('notifications.invoice_status_updated')),

    Action::make('markAsDeclined')
        ->label(__('labels.mark_as_declined'))
        ->icon('heroicon-m-x-circle')
        ->color('danger')
        ->requiresConfirmation()
        ->modalDescription(__('labels.manual_mark_declined_warning'))
        ->visible(static fn (Invoice $record) => $record->status === InvoiceStatus::Pending)
        ->action(function (Invoice $record, InvoiceService $invoiceService) {
            $invoiceService->markAsDeclined(new InvoiceIdList([InvoiceId::create($record->id)]));
        })
        ->successNotificationTitle(__('notifications.invoice_status_updated')),
]),
```

#### C. Bulk Actions on ListInvoices

Add bulk actions for marking multiple invoices as paid/declined at once:
```php
->toolbarActions([
    BulkActionGroup::make([
        Action::make('bulkMarkAsPaid')
            ->label(__('labels.mark_as_paid'))
            ->icon('heroicon-m-banknotes')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('labels.manual_mark_paid_bulk_warning'))
            ->action(function (Collection $records, InvoiceService $invoiceService) {
                $ids = $records
                    ->filter(fn (Invoice $r) => $r->status === InvoiceStatus::Pending)
                    ->map(fn (Invoice $r) => InvoiceId::create($r->id))
                    ->all();
                if ($ids !== []) {
                    $invoiceService->markAsPaid(new InvoiceIdList($ids));
                }
            })
            ->successNotificationTitle(__('notifications.invoice_status_updated'))
            ->deselectRecordsAfterCompletion(),

        Action::make('bulkMarkAsDeclined')
            ->label(__('labels.mark_as_declined'))
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('labels.manual_mark_declined_bulk_warning'))
            ->action(function (Collection $records, InvoiceService $invoiceService) {
                $ids = $records
                    ->filter(fn (Invoice $r) => $r->status === InvoiceStatus::Pending)
                    ->map(fn (Invoice $r) => InvoiceId::create($r->id))
                    ->all();
                if ($ids !== []) {
                    $invoiceService->markAsDeclined(new InvoiceIdList($ids));
                }
            })
            ->successNotificationTitle(__('notifications.invoice_status_updated'))
            ->deselectRecordsAfterCompletion(),

        DeleteBulkAction::make(),
    ]),
]),
```

### Step 3: Update InvoiceBatchInvoicesRelationManager

**File**: `app/Filament/Admin/Resources/InvoiceBatches/RelationManagers/InvoiceBatchInvoicesRelationManager.php`

The existing batch relation manager actions must be updated to:
1. Show confirmation warning messages about bypassing the normal flow (banking transaction import/matching)
2. Use `InvoiceService` methods instead of direct DB updates for consistency

Visibility stays unchanged: `Pending` invoices in `Pending` batches only.

Replace the existing `OnPendingInvoice::make` with an inline closure that adds the warning:

```php
use App\Domain\Invoices\InvoiceService;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;

// In the recordActions array — updated markAsPaid:
Action::make('markAsPaid')
    ->label(__('labels.mark_as_paid'))
    ->icon('heroicon-m-banknotes')
    ->requiresConfirmation()
    ->modalDescription(__('labels.manual_mark_paid_warning'))
    ->modalIcon('heroicon-m-exclamation-triangle')
    ->modalIconColor('danger')
    ->visible(OnPendingInvoice::make(...))
    ->action(static function (Invoice $record, InvoiceService $invoiceService): void {
        $invoiceService->markAsPaid(new InvoiceIdList([InvoiceId::create($record->id)]));
    })
    ->after(static fn (RelationManager $livewire) => $livewire->dispatch('refreshInvoicesTable')),

// Updated markAsDeclined:
Action::make('markAsDeclined')
    ->label(__('labels.mark_as_declined'))
    ->icon('heroicon-m-x-circle')
    ->color('danger')
    ->requiresConfirmation()
    ->modalDescription(__('labels.manual_mark_declined_warning'))
    ->modalIcon('heroicon-m-exclamation-triangle')
    ->modalIconColor('danger')
    ->visible(OnPendingInvoice::make(...))
    ->action(static function (Invoice $record, InvoiceService $invoiceService): void {
        $invoiceService->markAsDeclined(new InvoiceIdList([InvoiceId::create($record->id)]));
    })
    ->after(static fn (RelationManager $livewire) => $livewire->dispatch('refreshInvoicesTable')),
```

**Note**: The `removeFromBatch` action for `Open` invoices in `Open` batches should remain unchanged.

### Step 5: Update Language Files

**File**: `lang/nl/labels.php`

Add:
```php
'manual_mark_paid_warning' => 'Let op: normaal gesproken worden facturen automatisch als betaald gemarkeerd via het importeren van bankafschriften en het koppelen/afronden van banktransacties. Weet je zeker dat je deze factuur handmatig als betaald wilt markeren?',
'manual_mark_declined_warning' => 'Let op: normaal gesproken worden facturen geweigerd binnen een facturatieronde. Weet je zeker dat je deze factuur handmatig als geweigerd wilt markeren?',
'manual_mark_paid_bulk_warning' => 'Let op: normaal gesproken worden facturen automatisch als betaald gemarkeerd via het importeren van bankafschriften. Weet je zeker dat je de geselecteerde facturen handmatig als betaald wilt markeren?',
'manual_mark_declined_bulk_warning' => 'Let op: normaal gesproken worden facturen geweigerd binnen een facturatieronde. Weet je zeker dat je de geselecteerde facturen handmatig als geweigerd wilt markeren?',
```

---

## Implementation Order

1. **InvoiceService + InvoiceServiceImpl** (add `markAsDeclined()`)
2. **InvoicePolicy** (add `markAsPaid`, `markAsDeclined` methods)
3. **Language Files** (add warning labels)
4. **Filament Pages** (EditInvoice, ViewInvoice — add header actions)
5. **InvoicesTable** (add row actions + bulk actions)
6. **InvoiceBatchInvoicesRelationManager** (update existing actions: add warning confirmation, use InvoiceService)
7. **Tests**

---

## Testing Plan

### Unit Tests (`tests/Unit/Domain/Invoices/`)

#### InvoiceServiceImplTest (new or update existing)

1. **`test_mark_as_declined_updates_status()`**
   - Given pending invoices
   - When `markAsDeclined(InvoiceIdList)` is called
   - Then status changes to Declined

2. **`test_mark_as_declined_does_not_create_bookkeeping_records()`**
   - Verify no bookkeeping records are created when marking as declined

3. **`test_mark_as_paid_creates_bookkeeping_records()`**
   - Given pending invoices
   - When `markAsPaid(InvoiceIdList)` is called
   - Then status changes to Paid AND bookkeeping records are created

### Feature Tests (`tests/Feature/Filament/`)

#### InvoiceResourceTest (new file)

1. **`test_mark_as_paid_action_visible_for_pending()`**
   - Pending invoices show the mark-as-paid action

2. **`test_mark_as_paid_action_not_visible_for_open_paid_or_declined()`**
   - Open, paid and declined invoices do not show mark-as-paid

3. **`test_mark_as_paid_action_changes_status()`**
   - Execute mark-as-paid on a pending invoice
   - Assert status changed to Paid

4. **`test_bulk_mark_as_paid_updates_multiple_invoices()`**
   - Select multiple pending invoices
   - Execute bulk mark-as-paid
   - Assert all selected changed to Paid

5. **`test_manual_status_change_shows_warning_confirmation()`**
   - Verify confirmation modal appears with warning message

---

## Edge Cases & Considerations

- **Marking as Paid creates bookkeeping records**: This is important — the `InvoiceServiceImpl.markAsPaid()` already does this correctly
- **Marking as Declined does NOT create bookkeeping records**: Consistent with batch behavior. Declining is a cancellation, not a financial transaction
- **Only Pending invoices can be manually transitioned**: Open invoices must go through the batch flow (close batch → pending), then can be manually overridden if needed
- **Pending invoices in batches**: If an invoice in a pending batch is manually marked as paid or declined, it changes the batch's completion status. The batch's `completeBatch()` method checks for open/pending invoices — if one is marked paid, the batch can still be completed normally
- **The warning message must be clear**: Both individual row actions and bulk actions must show the confirmation warning about bypassing the normal flow

---

## Files Changed Summary

| Action | File |
|--------|------|
| Modify | `app/Domain/Invoices/InvoiceService.php` |
| Modify | `app/Domain/Invoices/InvoiceServiceImpl.php` |
| Modify | `app/Policies/InvoicePolicy.php` |
| Modify | `app/Filament/Admin/Resources/Invoices/Pages/EditInvoice.php` |
| Modify | `app/Filament/Admin/Resources/Invoices/Pages/ViewInvoice.php` |
| Modify | `app/Filament/Admin/Resources/Invoices/Tables/InvoicesTable.php` |
| Modify | `app/Filament/Admin/Resources/InvoiceBatches/RelationManagers/InvoiceBatchInvoicesRelationManager.php` |
| Possibly Remove | `app/Filament/Admin/Resources/InvoiceBatches/Helpers/OnPendingInvoice.php` |
| Modify | `lang/nl/labels.php` |
| Create | `tests/Unit/Domain/Invoices/InvoiceServiceImplTest.php` (or update existing) |
| Create | `tests/Feature/Filament/InvoiceResourceTest.php` |

---

## References

- Existing batch relation manager actions: `app/Filament/Admin/Resources/InvoiceBatches/RelationManagers/InvoiceBatchInvoicesRelationManager.php` (lines 48-67) — pattern for `markAsPaid` and `markAsDeclined`
- Existing batch edit page actions: `app/Filament/Admin/Resources/InvoiceBatches/Pages/EditInvoiceBatch.php` — pattern for `->requiresConfirmation()` and service injection in action closures
- InvoiceService pattern: `app/Domain/Invoices/InvoiceServiceImpl.php` — injection of `InvoiceRepository` and `BookkeepingRecordRepository`
- InvoicePolicy: `app/Policies/InvoicePolicy.php` — status-based authorization pattern
