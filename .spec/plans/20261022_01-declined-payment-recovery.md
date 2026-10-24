# Declined-payment recovery — implementation plan

Date: 2026-10-22
Backlog step: 1 of 13 in [Prioritize the confirmed backlog and decide the plan split](../maps/20261021-missing-features/tickets/07-prioritize-confirmed-backlog-and-decide-plan-split.md)
Decisions source: [Judge SEPA & payment robustness candidates](../maps/20261021-missing-features/tickets/04-judge-sepa-payment-robustness-candidates.md), gap 3

## Overview

Make `Declined` a recoverable state for invoices. A declined invoice can be reopened, retried into a new collection
round, or settled by an incoming manual payment. Every decline is recorded as history, and every decline emits the
domain event the later declined-SEPA notice plan consumes.

This plan also owns the prerequisite defect that currently makes automatic reversal detection decline the wrong
transaction: `resolveMatching` calls `linkReversal` with the pair inverted, and the unit test encodes that inversion.

## Current situation

- `InvoiceStatus` has four cases (`Open`, `Pending`, `Paid`, `Declined`) and no transition out of `Declined`.
  `InvoiceRepositoryDb::markAsDeclined` only updates rows whose status is `Pending`; it records no reason, date or
  reversal link.
- `InvoicePolicy::attachBankTransaction` and `markPaid` require `Pending`, so a declined invoice cannot be settled by
  the manual transfer that often follows a failed direct debit.
- `BankTransactionServiceImpl::resolveMatching` (line 60–62) calls
  `linkReversal($result->reversedByTransactionId, $id)`. The repository contract is
  `linkReversal(reversalId, originalId)`: it writes `reversed_by_transaction_id` on the **reversal** row and copies
  references from the **original**. The automatic path therefore writes the link on the original and reads references
  from the new arrival, declining nothing.
- `tests/Unit/Domain/Bookkeeping/Banking/BankTransactions/BankTransactionServiceImplTest.php` encodes the inverted
  order at lines 202–209. `tests/Feature/Scenarios/PaymentMatchingScenarioTest.php` covers a normal payment but not a
  reversal, so no end-to-end test would have caught the defect.
- `EditSepaBatch::addInvoices` only offers `status = Open` and `sepa_batch_id IS NULL`, so a declined invoice cannot
  re-enter a batch.
- `InvoiceServiceImpl::markAsPending` dispatches `SendInvoiceEmail` unconditionally, and the batch-close listener
  queues it for every pending invoice. `SendInvoiceEmail` skips when `invoices.sent_at` is set, so a retry would be
  collected without a fresh pre-notification unless `sent_at` is cleared on reopen.
- `unlinkReversal` calls `InvoiceService::markAsPending`, which updates any status. Once reopen exists, unlinking a
  reversal after a retry would clobber the reopened invoice back to `Pending` and queue a premature mail.
- There is no invoice decline event. The later declined-SEPA notice plan needs one emitted from both decline paths
  (automatic reversal and manual action).

## Planned changes

| Change | Main files |
|---|---|
| Fix the inverted automatic reversal link and guard the resolver loop | `BankTransactionServiceImpl`, `BankTransactionDbRepository` |
| Add decline history (table, model, relation) and optional note | migration, `InvoiceDecline`, `Invoice` |
| Emit `InvoicePaymentDeclined` from the single decline funnel | `InvoiceServiceImpl`, `Events/InvoicePaymentDeclined` |
| Add the reopen transition (service, repository, policy, UI) | `InvoiceService`, `InvoiceRepositoryDb`, `InvoicePolicy`, `InvoiceStateActions` |
| Add batch-scoped bulk retry into a chosen open batch | `SepaBatchService`, `SepaBatchRepositoryDb`, `EditSepaBatch` |
| Allow a manual payment to settle a declined invoice | `InvoicePolicy`, `AttachInvoiceAction`, `Invoice` scope |
| Stop `unlinkReversal` from clobbering a reopened invoice | `InvoiceService`, `InvoiceRepository`, `BankTransactionServiceImpl` |
| Show decline history on the invoice | `InvoiceDeclinesRelationManager`, invoice pages |

## Fix the inverted automatic reversal link

`app/Domain/Bookkeeping/Banking/BankTransactions/BankTransactionServiceImpl.php` — the current arrival (`$id`) is the
reversal; the match result points at the original it reverses:

```php
if ($result->reversedByTransactionId !== null) {
    $this->linkReversal($id, $result->reversedByTransactionId);
}
```

`resolveMatching` snapshots the unresolved ids before the loop. When a reversal is linked, `linkReversal` marks both
rows resolved, but the loop still reaches the original later and can downgrade it to `unresolvable`. Guard the
downgrade in `app/Infrastructure/Bookkeeping/Banking/BankTransactions/BankTransactionDbRepository.php` so only rows
still unresolved are touched:

```php
#[Override]
public function markAsUnresolvable(BankTransactionId $bankTransactionId): void
{
    BankTransaction::query()
        ->whereKey($bankTransactionId->value)
        ->where('resolve_status', ResolveStatus::Unresolved)
        ->update(['resolve_status' => ResolveStatus::Unresolvable]);
}
```

Update the unit test to the correct order and add the end-to-end scenario described in the test section.

## Decline history

Create `database/migrations/2026_10_22_000000_create_invoice_declines_table.php`:

```php
Schema::create('invoice_declines', static function (Blueprint $table): void {
    $table->id();
    $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
    $table->foreignId('sepa_batch_id')->nullable()->constrained('sepa_batches')->nullOnDelete();
    $table->foreignId('bank_transaction_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
    $table->timestamp('declined_at');
    $table->text('note')->nullable();
    $table->timestamps();

    $table->index(['invoice_id', 'declined_at']);
});
```

Add `app/Models/InvoiceDecline.php`:

```php
#[Guarded(['id', 'created_at', 'updated_at'])]
final class InvoiceDecline extends Model
{
    use HasFactory;

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<SepaBatch, $this> */
    public function sepaBatch(): BelongsTo
    {
        return $this->belongsTo(SepaBatch::class);
    }

    /** @return BelongsTo<BankTransaction, $this> */
    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    #[Override]
    protected function casts(): array
    {
        return ['declined_at' => 'datetime'];
    }
}
```

Add the relation to `app/Models/Invoice.php`:

```php
/** @return HasMany<InvoiceDecline, $this> */
public function declines(): HasMany
{
    return $this->hasMany(InvoiceDecline::class)->orderByDesc('declined_at');
}
```

Add `database/factories/InvoiceDeclineFactory.php` following the sibling factories:

```php
public function definition(): array
{
    return [
        'invoice_id' => Invoice::factory(),
        'sepa_batch_id' => null,
        'bank_transaction_id' => null,
        'declined_at' => now(),
        'note' => null,
    ];
}
```

Historic invoices already in `Declined` are intentionally not backfilled: the retry path queries by status, and a
backfilled timestamp would be a guess. New declines are recorded from this change onward.

## Decline domain event

Add `app/Domain/Billing/Invoices/Events/InvoicePaymentDeclined.php`:

```php
final readonly class InvoicePaymentDeclined
{
    public function __construct(
        public InvoiceId $invoiceId,
    ) {}
}
```

Emit it from the single decline funnel in `app/Domain/Billing/Invoices/InvoiceServiceImpl.php`. The repository returns
the ids it actually transitioned, so the event never fires for an invoice that was already `Paid` or `Declined`:

```php
public function __construct(
    private InvoiceRepository $invoiceRepository,
    private BookkeepingRecordRepository $bookkeepingRepository,
    private JobDispatcher $jobDispatcher,
    private Dispatcher $eventDispatcher,
) {}

#[Override]
public function markAsDeclined(
    InvoiceIdList $ids,
    ?BankTransactionId $reversalTransactionId = null,
    ?string $note = null,
): void {
    $declinedIds = $this->invoiceRepository->markAsDeclined($ids, $reversalTransactionId, $note);

    foreach ($declinedIds->ids as $invoiceId) {
        $this->eventDispatcher->dispatch(new InvoicePaymentDeclined($invoiceId));
    }
}
```

Pass all three arguments explicitly from every call site, mirroring `PurchaseOrderService::markAsPaymentDeclined`:

- `BankTransactionServiceImpl::linkReversal` → `markAsDeclined($invoiceIds, $reversalId, null)`
- the manual Filament action → `markAsDeclined($ids, null, $note)`

## Persist declines and reopen invoices

Change `app/Domain/Billing/Invoices/InvoiceRepository.php`:

```php
public function markAsDeclined(
    InvoiceIdList $ids,
    ?BankTransactionId $reversalTransactionId = null,
    ?string $note = null,
): InvoiceIdList;

public function reopen(InvoiceIdList $ids): InvoiceIdList;

public function markAsPendingIfDeclined(InvoiceIdList $ids): void;
```

Implement in `app/Infrastructure/Billing/Invoices/InvoiceRepositoryDb.php`:

```php
#[Override]
public function markAsDeclined(
    InvoiceIdList $ids,
    ?BankTransactionId $reversalTransactionId = null,
    ?string $note = null,
): InvoiceIdList {
    return DB::transaction(function () use ($ids, $reversalTransactionId, $note): InvoiceIdList {
        $declinable = Invoice::query()
            ->whereIn('id', $ids->values())
            ->where('status', InvoiceStatus::Pending)
            ->get(['id', 'sepa_batch_id']);

        $declinedIds = new InvoiceIdList(
            $declinable->pluck('id')->map(InvoiceId::create(...))->all(),
        );

        foreach ($declinable as $invoice) {
            InvoiceDecline::query()->create([
                'invoice_id' => $invoice->id,
                'sepa_batch_id' => $invoice->sepa_batch_id,
                'bank_transaction_id' => $reversalTransactionId?->value,
                'declined_at' => now(),
                'note' => $note,
            ]);
        }

        if ($declinedIds->ids !== []) {
            Invoice::query()
                ->whereIn('id', $declinedIds->values())
                ->update(['status' => InvoiceStatus::Declined]);
        }

        return $declinedIds;
    });
}

#[Override]
public function reopen(InvoiceIdList $ids): void
{
    Invoice::query()
        ->whereIn('id', $ids->values())
        ->where('status', InvoiceStatus::Declined)
        ->whereNull('credit_invoice_id')
        ->update([
            'status' => InvoiceStatus::Open,
            'sepa_batch_id' => null,
            'sent_at' => null,
        ]);
}

#[Override]
public function markAsPendingIfDeclined(InvoiceIdList $ids): void
{
    Invoice::query()
        ->whereIn('id', $ids->values())
        ->where('status', InvoiceStatus::Declined)
        ->update(['status' => InvoiceStatus::Pending]);
}
```

Notes:

- Clearing `sent_at` is required for the retry to send a fresh invoice mail with the new incassodatum. Without it,
  `SendInvoiceEmail` returns early because the original mail was already sent.
- Clearing `sepa_batch_id` makes the invoice eligible for the existing “add invoices” picker and for the new retry
  action.
- The credit guards avoid retrying an invoice that was already credited. The service returns only the ids it actually
  reopened, so callers attach only those.
- The target batch sets `invoices.date` to its own `invoice_date` on close. That is current behaviour for every
  invoice attached to a batch, and a retried invoice inherits it. Its original bookkeeping records remain attached to
  the original year because `createForInvoice` is idempotent.

Update `app/Domain/Billing/Invoices/InvoiceService.php`:

```php
public function markAsDeclined(
    InvoiceIdList $ids,
    ?BankTransactionId $reversalTransactionId = null,
    ?string $note = null,
): void;

public function reopen(InvoiceIdList $ids): InvoiceIdList;

public function restoreDeclined(InvoiceIdList $ids): void;
```

`InvoiceServiceImpl::reopen` delegates to the repository. `restoreDeclined` updates only still-declined invoices and
creates their bookkeeping records, without sending a mail:

```php
#[Override]
public function reopen(InvoiceIdList $ids): void
{
    $this->invoiceRepository->reopen($ids);
}

#[Override]
public function restoreDeclined(InvoiceIdList $ids): void
{
    $this->invoiceRepository->markAsPendingIfDeclined($ids);
    $this->bookkeepingRepository->createForInvoice($ids);
}
```

Use `restoreDeclined` in `BankTransactionServiceImpl::unlinkReversal` instead of `markAsPending`. Undoing a reversal
must not drag a reopened (or manually settled) invoice back to `Pending`. This also stops the unlink path from queuing
a second invoice mail: the original mail can no longer be un-sent, and a retried invoice gets a fresh mail when its new
batch closes because `reopen` cleared `sent_at`.

## Reopen policy and UI

Add to `app/Policies/InvoicePolicy.php`:

```php
public function reopen(User $user, Model $invoice): bool
{
    Assert::isInstanceOf($invoice, Invoice::class);

    return $user->can('update_invoices', $invoice)
        && $invoice->status === InvoiceStatus::Declined
        && $invoice->credit_invoice_id === null
        && $invoice->creditInvoice()->doesntExist();
}
```

Lift the manual-payment block by widening the two status guards:

```php
public function markPaid(User $user, Model $invoice): bool
{
    Assert::isInstanceOf($invoice, Invoice::class);

    if (!in_array($invoice->status, [InvoiceStatus::Pending, InvoiceStatus::Declined], true)) {
        return false;
    }

    // existing permission and attached-transaction checks
}

public function attachBankTransaction(User $user, Model $invoice): bool
{
    Assert::isInstanceOf($invoice, Invoice::class);

    if (!in_array($invoice->status, [InvoiceStatus::Pending, InvoiceStatus::Declined], true)) {
        return false;
    }

    return $user->can('update_invoices') && $user->can('view_bank_transactions');
}
```

In `app/Filament/Admin/Resources/Invoices/Actions/InvoiceStateActions.php`:

- Add a `note` `Textarea` to the existing `markAsDeclined` action and pass it as the third argument.
- Add a `reopen` action visible through `can('reopen', $record)`, calling `InvoiceService::reopen`. Reuse the existing
  redirect/refresh/notification pattern.

```php
Action::make('reopen')
    ->label(__('labels.reopen_invoice'))
    ->icon('heroicon-m-arrow-uturn-left')
    ->color('warning')
    ->requiresConfirmation()
    ->modalDescription(__('labels.reopen_invoice_warning'))
    ->visible(static fn (Invoice $record): bool => auth()->user()?->can('reopen', $record) ?? false)
    ->action(static function (Invoice $record, InvoiceService $invoiceService): void {
        $invoiceService->reopen(new InvoiceIdList([InvoiceId::create($record->id)]));
    })
    ->successNotificationTitle(__('notifications.invoice_reopened'))
    ->after(static function (Component $livewire): void {
        $livewire->dispatch('refresh');
    }),
```

Replace the duplicated three state actions in
`app/Filament/Admin/Resources/Invoices/Tables/InvoicesTable.php` with `...InvoiceStateActions::make()` and keep the
existing `createCredit` action beside it. This makes the note and reopen action available everywhere the shared
actions are used, including the SEPA batch invoice relation manager, without a third copy.

## Retry declined payments into a chosen open batch

Add `attachInvoices` to `app/Domain/Billing/Payment/SepaBatchRepository.php` and implement it in
`app/Infrastructure/Billing/Payment/SepaBatchRepositoryDb.php`:

```php
#[Override]
public function attachInvoices(SepaBatchId $batchId, InvoiceIdList $ids): int
{
    $batch = SepaBatch::findOrFail($batchId->value);

    if ($batch->status !== SepaBatchStatus::Open) {
        throw new DomainException('Only an open batch can receive retried invoices.');
    }

    return Invoice::query()
        ->whereIn('id', $ids->values())
        ->whereNull('sepa_batch_id')
        ->where('status', InvoiceStatus::Open)
        ->update(['sepa_batch_id' => $batchId->value]);
}
```

Expose it through `app/Domain/Billing/Payment/SepaBatchService.php` and `SepaBatchServiceImpl`:

```php
public function attachInvoices(SepaBatchId $batchId, InvoiceIdList $ids): int;
```

In `app/Filament/Admin/Resources/SepaBatches/Pages/EditSepaBatch.php`, add a header action before `closeBatch`:

```php
Action::make('retryDeclined')
    ->label(__('labels.retry_declined_payments'))
    ->icon('heroicon-m-arrow-path')
    ->color('warning')
    ->visible(static fn (SepaBatch $record): bool =>
        (auth()->user()?->can('update_invoices') ?? false)
        && $record->invoices()->where('status', InvoiceStatus::Declined)->exists())
    ->disabled(static fn (): bool => SepaBatch::query()
        ->where('status', SepaBatchStatus::Open)
        ->doesntExist())
    ->tooltip(static fn (): ?string => SepaBatch::query()
        ->where('status', SepaBatchStatus::Open)
        ->doesntExist()
            ? __('labels.no_open_batch_for_retry')
            : null)
    ->schema([
        Select::make('target_batch_id')
            ->label(__('labels.target_batch'))
            ->options(static fn (SepaBatch $record): array => SepaBatch::query()
                ->where('status', SepaBatchStatus::Open)
                ->whereKeyNot($record->id)
                ->orderBy('invoice_date')
                ->get()
                ->mapWithKeys(static fn (SepaBatch $batch): array => [
                    $batch->id => $batch->invoice_date->format('d-m-Y'),
                ])
                ->all())
            ->required()
            ->helperText(__('labels.retry_declined_target_help')),
    ])
    ->requiresConfirmation()
    ->action(static function (
        SepaBatch $record,
        array $data,
        InvoiceService $invoiceService,
        SepaBatchService $batchService,
    ): void {    
        $declinedIds = new InvoiceIdList(
            $record->invoices()
                ->where('status', InvoiceStatus::Declined)
                ->whereNull('credit_invoice_id')
                ->pluck('id')
                ->map(InvoiceId::create(...))
                ->all(),
        );

        $invoiceService->reopen($declinedIds);

        $batchService->attachInvoices(
            SepaBatchId::create((int) $data['target_batch_id']),
            $declinedIds,
        );
    })
    ->successNotificationTitle(__('notifications.declined_payments_retried'))
    ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refreshInvoicesTable')),
```

The source batch keeps the decline history rows that point at it, so the action remains explainable after the invoices
leave the batch.

## Show decline history on the invoice

Add `app/Filament/Admin/Resources/Invoices/RelationManagers/InvoiceDeclinesRelationManager.php`:

```php
final class InvoiceDeclinesRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'declines';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.decline_history');
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('declined_at')
                    ->label(__('labels.declined_at'))
                    ->dateTime(),
                TextColumn::make('sepaBatch.invoice_date')
                    ->label(__('labels.source_batch'))
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('bankTransaction.id')
                    ->label(__('labels.reversal_transaction'))
                    ->formatStateUsing(static fn (?int $state): string => $state === null ? '—' : '#' . $state)
                    ->url(static fn (InvoiceDecline $record): ?string => $record->bank_transaction_id === null
                        ? null
                        : BankTransactionResource::getUrl('view', ['record' => $record->bank_transaction_id])),
                TextColumn::make('note')
                    ->label(__('labels.decline_note'))
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->recordActions([]);
    }
}
```

Register it in `ViewInvoice::getAllRelationManagers()` and `EditInvoice::getAllRelationManagers()`, and update the
existing relation-manager assertion in `InvoiceResourceTest`. The relation is read-only; the history is written by the
repository, never edited by an administrator.

## Make a manual payment settle a declined invoice

`app/Filament/Admin/Resources/BankTransactions/Actions/AttachInvoiceAction.php` currently lists invoices through the
`openOrPending` scope. Add a narrower scope to `app/Models/Invoice.php` and use it there:

```php
#[Scope]
protected function outstandingOrDeclined(Builder $query): Builder
{
    return $query->whereIn('status', [
        InvoiceStatus::Open,
        InvoiceStatus::Pending,
        InvoiceStatus::Declined,
    ]);
}
```

Leave `openOrPending` untouched for the dashboard widgets. The invoice-side `AttachBankTransactionAction` already lists
all open transactions and becomes visible on a declined invoice through the widened policy. Completing the attached
transaction already marks the invoice `Paid` in `BankTransactionDbRepository::complete` without a status guard.

## Labels

Add to `lang/nl/labels.php`:

```php
'reopen_invoice' => 'Factuur heropenen',
'reopen_invoice_warning' => 'De factuur gaat terug naar Open en kan opnieuw in een SEPA ronde worden geplaatst.',
'decline_note' => 'Toelichting (optioneel)',
'decline_history' => 'Weigeringsgeschiedenis',
'declined_at' => 'Geweigerd op',
'source_batch' => 'Bron SEPA ronde',
'retry_declined_payments' => 'Geweigerde betalingen opnieuw innen',
'target_batch' => 'Doel SEPA ronde',
'retry_declined_target_help' => 'Kies een open SEPA ronde. De facturen worden heropend en aan die ronde toegevoegd.',
'no_open_batch_for_retry' => 'Er is geen open SEPA ronde om de facturen aan toe te voegen.',
```

Add to `lang/nl/notifications.php`:

```php
'invoice_reopened' => 'Factuur heropend',
'declined_payments_retried' => 'Geweigerde betalingen opnieuw ingepland',
```

## Tests

All new tests are PHPUnit classes and use the existing factories. Create the new test files with
`./Taskfile artisan make:test --phpunit <name>` and the migration with `./Taskfile artisan make:migration`. Run each
file after changing it.

### Unit — reversal order

`tests/Unit/Domain/Bookkeeping/Banking/BankTransactions/BankTransactionServiceImplTest.php`:

- `test_resolve_matching_with_reversal_calls_link_reversal_and_mark_resolved` must expect
  `linkReversal($bankTransactionId, $originalId)`, `getAttachedInvoiceIds($originalId)`,
  `markAsDeclined($emptyInvoiceIds, $bankTransactionId, null)` and
  `getAttachedPurchaseOrderIds($originalId)`.
- `test_unlink_reversal_marks_attached_references_as_pending` must expect `restoreDeclined($invoiceIds)` instead of
  `markAsPending`.

Update the expectation helpers accordingly:

```php
// tests/Unit/Domain/Billing/Invoices/InvoiceServiceExpectation.php
public function expectsMarkAsDeclined(
    InvoiceIdList $ids,
    ?BankTransactionId $reversalTransactionId = null,
    ?string $note = null,
): void {
    $this->mock
        ->expects('markAsDeclined')
        ->with(equalTo($ids), equalTo($reversalTransactionId), equalTo($note));
}

public function expectsRestoreDeclined(InvoiceIdList $ids): void
{
    $this->mock->expects('restoreDeclined')->with(equalTo($ids));
}
```

```php
// tests/Unit/Domain/Billing/Invoices/CreateInvoiceExpectation.php
public function expectsMarkAsDeclined(
    InvoiceIdList $ids,
    ?BankTransactionId $reversalTransactionId,
    ?string $note,
    InvoiceIdList $return,
): void {
    $this->mock
        ->expects('markAsDeclined')
        ->with(equalTo($ids), equalTo($reversalTransactionId), equalTo($note))
        ->andReturn($return);
}

public function expectsReopen(InvoiceIdList $ids, InvoiceIdList $return): void
{
    $this->mock->expects('reopen')->with(equalTo($ids))->andReturn($return);
}

public function expectsMarkAsPendingIfDeclined(InvoiceIdList $ids): void
{
    $this->mock->expects('markAsPendingIfDeclined')->with(equalTo($ids));
}
```

### Unit — invoice service

`tests/Unit/Domain/Billing/Invoices/InvoiceServiceImplTest.php`:

- Inject `EventDispatcherExpectation` as the fourth service dependency.
- `test_mark_as_declined_dispatches_event_for_each_transitioned_invoice`: repository returns two ids; expect
  `InvoicePaymentDeclined` twice.
- `test_mark_as_declined_does_not_dispatch_when_nothing_transitioned`: repository returns an empty list; use
  `expectsNotToDispatch`.
- `test_reopen_delegates_to_repository`.
- `test_restore_declined_updates_only_declined_and_creates_bookkeeping_records`.

### Feature — end-to-end reversal

Add to `tests/Feature/Scenarios/PaymentMatchingScenarioTest.php`:

```php
public function test_a_returned_direct_debit_declines_the_invoice_and_records_the_reversal(): void
{
    $this->travelToDate('2026-08-15');

    $member = Member::factory()
        ->has(PaymentInformation::factory()->state([
            'banking_account_number' => self::MEMBER_IBAN,
            'banking_bic' => 'ABNANL2A',
        ]))
        ->createOneQuietly();

    $batch = SepaBatch::factory()->createQuietly();
    $invoice = $this->createInvoice(
        $member,
        [['description' => 'Lidmaatschap augustus', 'price' => 88.00, 'quantity' => 1]],
        ['date' => '2026-08-15', 'sepa_batch_id' => $batch->id],
    );
    $this->markInvoiceAsPending($invoice);

    $bankAccount = BankAccount::factory()->create();
    $this->importMt940Statement($this->mt940Statement($bankAccount, [
        [
            'date' => '2026-08-20',
            'debitCredit' => 'C',
            'amount' => 88.00,
            'counterpartyIban' => self::MEMBER_IBAN,
            'description' => 'Lidmaatschap augustus',
        ],
        [
            'date' => '2026-08-22',
            'debitCredit' => 'D',
            'amount' => 88.00,
            'counterpartyIban' => self::MEMBER_IBAN,
            'description' => 'Lidmaatschap augustus',
        ],
    ]));

    $original = BankTransaction::query()->where('amount', '>', 0)->sole();
    $reversal = BankTransaction::query()->where('amount', '<', 0)->sole();

    $this->assertDatabaseHas('bank_transactions', [
        'id' => $reversal->id,
        'reversed_by_transaction_id' => $original->id,
    ]);
    $this->assertDatabaseHas('bank_transactions', [
        'id' => $original->id,
        'resolve_status' => ResolveStatus::Resolved->value,
    ]);
    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'status' => InvoiceStatus::Declined->value,
    ]);
    $this->assertDatabaseHas('invoice_declines', [
        'invoice_id' => $invoice->id,
        'sepa_batch_id' => $batch->id,
        'bank_transaction_id' => $reversal->id,
    ]);
}
```

### Feature — repository transitions

Add `tests/Feature/Infrastructure/Billing/Invoices/InvoiceRepositoryDbTransitionTest.php`:

- `test_mark_as_declined_records_the_source_batch_and_reversal_link`
- `test_mark_as_declined_returns_only_transitioned_ids`
- `test_mark_as_declined_does_not_record_a_paid_invoice`
- `test_reopen_returns_declined_invoices_to_open_and_detaches_them`
- `test_reopen_clears_sent_at`
- `test_reopen_skips_invoices_with_a_credit_invoice`
- `test_reopen_returns_only_transitioned_ids`
- `test_mark_as_pending_if_declined_leaves_other_statuses_untouched`

Add to `tests/Feature/Infrastructure/Billing/Payment/SepaBatchRepositoryDbTest.php`:

- `test_attach_invoices_attaches_open_unbatched_invoices`
- `test_attach_invoices_ignores_batched_and_non_open_invoices`
- `test_attach_invoices_throws_for_a_non_open_batch`

And to `tests/Unit/Domain/Billing/Payment/SepaBatchServiceImplTest.php` plus
`SepaBatchRepositoryExpectation`:

- `test_attach_invoices` delegates and returns the count.

### Feature — Filament

Extend `tests/Feature/Filament/Admin/Resources/InvoiceResourceTest.php`:

- `test_mark_as_declined_stores_an_optional_note`
- `test_reopen_action_is_visible_for_a_declined_invoice`
- `test_reopen_action_is_hidden_for_pending_and_paid_invoices`
- `test_reopen_action_returns_the_invoice_to_open_and_detaches_it`
- `test_attach_bank_transaction_action_is_visible_for_a_declined_invoice`
- `test_attach_bank_transaction_action_attaches_to_a_declined_invoice`
- extend `test_admin_sees_financial_relation_managers_on_invoice` with `InvoiceDeclinesRelationManager`

Extend `tests/Feature/Filament/BankTransaction/BankTransactionResourceTest.php`:

- `test_attach_invoice_action_offers_a_declined_invoice`
- `test_attach_invoice_action_attaches_a_declined_invoice`

Extend `tests/Feature/Filament/SepaBatches/SepaBatchResourceTest.php`:

- `test_retry_declined_action_moves_declined_invoices_into_the_chosen_open_batch`
- `test_retry_declined_action_is_hidden_when_the_batch_has_no_declined_invoices`
- `test_retry_declined_action_rejects_a_non_open_target_batch`

The retry test must also assert `sent_at` is `null` after the move, because the next batch close has to send a fresh
invoice mail.

## Out of scope

- Purchase-order `reopenPayment` exposure. The repository and policy already exist, but the map scopes this plan to
  invoices; exposing the PO action is a separate follow-up.
- Automatic retries. Retries are manual and uncapped by decision; no scheduler, no retry counters.
- Bank return codes. The Rabobank MT940 engine discards `/RTRN/`, and no column stores one, so decline history carries
  only date, source batch, reversal transaction and note.
- Dunning states, surcharges and write-off. `Declined` remains terminal for money that never arrives; the escalation
  mechanics plan owns those states.
- Re-sending the decline notice. That is the later declined-SEPA notice plan, which consumes `InvoicePaymentDeclined`.
- Re-declining an invoice that was already completed (`Paid`) before its reversal. The confirmed shape keeps
  `markAsDeclined` on the `Pending` path. Widening it to `Paid → Declined` needs the pre-decline status stored so
  `unlinkReversal` can restore it; that is a separate status-history concern and a follow-up, not part of this plan.

## Verification

```bash
./Taskfile artisan test --compact tests/Unit/Domain/Bookkeeping/Banking/BankTransactions/BankTransactionServiceImplTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Invoices/InvoiceServiceImplTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/SepaBatchServiceImplTest.php
./Taskfile artisan test --compact tests/Feature/Scenarios/PaymentMatchingScenarioTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Invoices/InvoiceRepositoryDbTransitionTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Payment/SepaBatchRepositoryDbTest.php
./Taskfile artisan test --compact tests/Feature/Filament/Admin/Resources/InvoiceResourceTest.php
./Taskfile artisan test --compact tests/Feature/Filament/SepaBatches/SepaBatchResourceTest.php
./Taskfile stan
./Taskfile lint
```
