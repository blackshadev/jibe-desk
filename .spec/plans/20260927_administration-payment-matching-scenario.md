# Administration Payment-Matching Scenario Test

Date: 2026-09-27

## Overview

Add a new scenario test in `tests/Feature/Scenarios/` that walks the financial administration end to end: a member's invoice and a purchase order are created and marked pending, bank transactions are imported from an MT940 statement and automatically linked to both, and completing the transactions settles the invoice and purchase order. The minimum flow lives in one scenario test; additional suggested test cases cover idempotent re-import, unmatched/oversized payments, reversals, internal transfers, matching windows, closest-amount matching, retry-matching, and statement integrity warnings.

## Current Situation

Scenario tests are story-style feature tests that freeze time and drive the real Filament/Livewire admin UI plus real domain services against a seeded database.

- `tests/Feature/Scenarios/MemberLifecycleScenario.php` is the abstract base. It uses `Tests\Concerns\TestsMemberLifecycle` (helpers: `seedBillingFixtures`, `travelToDate`, `resetTime`, `registerMember`, `joinActivity`, `updateMember`, `generateInvoiceFor`, `invoiceFor`, `assertInvoiceLine`, `defaultMembership`, `activityNamed`) and `Tests\Concerns\WithAuthorizedUser` (grants a `full_access` user). `setUp()` seeds `CostCenterSeeder`, `ActivitySeeder`, `MembershipSeeder`.
- `tests/Feature/Scenarios/WindsurferLifetimeScenarioTest.php` is the only concrete scenario so far; it uses `travelToDate`, `generateInvoiceFor`, `invoiceFor`, `assertInvoiceLine` and `assertDatabaseHas` to narrate a billing lifecycle.
- Invoicing: `Invoice` (`App\Models\Invoice`) has `status` cast to `App\Domain\Invoices\InvoiceStatus` (`Open`, `Pending`, `Paid`, `Declined`). `generateInvoiceFor` produces an `Open` invoice per member per month. `InvoiceService::markAsPending/markAsPaid` (`App\Domain\Invoices\InvoiceServiceImpl`) flip the status and create `BookkeepingRecord`s via `BookkeepingRecordRepository::createForInvoice` (idempotent: `whereNotExists` on `reference_type`/`reference_id`). `Invoice::total` is a `CompoundPrice` of `sum(price * quantity)` per line (ex-VAT).
- Purchase orders: `PurchaseOrder` (`App\Models\PurchaseOrder`) has `status` cast to `App\Domain\PurchaseOrders\PurchaseOrderStatus` (`Open`, `Pending`, `Paid`, `Declined`). Created via `App\Filament\Admin\Resources\PurchaseOrders\Pages\CreatePurchaseOrder` (requires `image_path` upload on create; `lines` repeater with `description`, `price`, `price_vat`, `cost_center_id`). `PurchaseOrderStateActions` provides `markAsApproved` (→ status `Pending` + bookkeeping records; requires complete PO: creditor name, creditor IBAN, lines with cost centers), `markAsPaid` (requires a linked `BankTransaction` with status `Completed`), `markAsDeclined` (requires `declined_reason`).
- Bank transactions: `App\Models\BankTransaction` (`bank_transactions` table) with `status` (`App\Domain\BankTransactions\BankTransactionStatus`: `Open`, `Completed`) and `resolve_status` (`App\Domain\BankTransactions\ResolveStatus`: `Unresolved`, `Resolved`, `Unresolvable`). Computed `matched_amount` = sum of attached `invoice_lines.price * quantity` + sum of attached standalone `bookkeeping_records.amount_price` (`unattached()` scope = `whereNull('reference_id')`) − sum of attached `purchase_order_lines.price`; `unmatched_amount` = `amount − matched_amount`.
- MT940 import: `App\Domain\BankTransactions\BankTransactionImportService::importFromFile` (`App\Infrastructure\BankTransactions\BankTransactionImportServiceImpl`) parses with `kingsquare/php-mt940` (fork `fruitl00p/php-mt940` v2). It resolves the club's own `BankAccount` from `:25:`, upserts a `BankStatement`, checks balance integrity (opening + Σ relative amounts = closing), and creates `BankTransaction`s keyed by a sha256 `import_hash` (`date|price|description|counterparty account|own account`) — re-imports are skipped. Triggered from the admin UI by `App\Filament\Admin\Actions\ImportMt940Action` (action name `importMt940`, FileUpload field `mt940_file`) on `ListBankTransactions`, which dispatches `App\Domain\Jobs\MatchBankTransactionsJob` when anything was imported (queue is `sync` in `phpunit.xml`, so it runs inline).
- Automatic matching: `MatchBankTransactionsJob` → `BankTransactionService::resolveMatching` (`App\Domain\BankTransactions\BankTransactionServiceImpl`) → `TransactionMatchingService::findMatch` (`TransactionMatchingServiceImpl`). Decision order: counterparty IBAN is one of our own `BankAccount`s → internal transfer; `amount > 0` → `InvoiceRepository::findMatchingCredit` (status `Pending`, invoice date ±30 days, counterparty IBAN equals member's `paymentInformation.banking_account_number`, closest total, final guard `|total − amount| ≤ 0.01`); `amount <= 0` → `PurchaseOrderRepository::findMatchingDebit` (status `Open|Pending`, date ±30 days, `creditor_iban` exact match, `|total − amount| ≤ 0.01`); fallback → reversal match (same counterparty + identical description, opposite amount, within 56 days back). On match it attaches `bank_transaction_references` rows and sets `resolve_status = resolved`; otherwise `unresolvable`. **Matching never sets a document to paid and never completes a transaction.**
- Completion: `CompleteBankTransactionAction` (action name `complete`, header action on `App\Filament\Admin\Resources\BankTransactions\Pages\ViewBankTransaction`; disabled unless `|unmatched_amount| < 0.01`) → `BankTransactionService::complete` → `BankTransactionDbRepository::complete` (throws `App\Domain\BankTransactions\CouldNotCompleteTransaction` if `|unmatched_amount| ≥ 0.01`; sets the transaction `Completed` and stamps `bank_transaction_id` onto the attached invoice/PO bookkeeping records) → `InvoiceService::markAsPaid` → `PurchaseOrderService::markAsPaid` (guard: `hasCompletedTransactions` passes because the transaction was completed first).
- MT940 parsing details that matter for fixtures (verified against `fruitl00p/php-mt940` `Engine`, `Engine\Rabo`): a file whose first line contains `:940:` is parsed by the Rabo engine. `getAccount()` (the counterparty, stored as `banking_account_number`) comes from the line directly under `:61:` (the contra-account line). `getValueTimestamp` is the `YYMMDD` right after `:61:`. The amount is the `[C|D]` + `[\d,\.]+` + `N` segment; `getRelativePrice()` is negative for `D`. The description is the `/REMI/…` content of `:86:`. `Mt940::$removeIBAN` is set to `false` by the importer, so IBANs survive untouched. `tests/Fixtures/mt940/rabo.mta` is a proven example of this shape; `App\Console\Commands\GenerateMt940Command` shows a programmatic writer.

## Planned Changes

A summary of what will be created; no production code changes are needed.

- A new test concern trait `Tests\Concerns\TestsBankingLifecycle` with helpers to create and approve purchase orders, mark invoices pending, build an MT940 statement string, import it through the admin `importMt940` action (which also triggers matching), and complete a transaction through the `complete` page action.
- A new scenario test `tests/Feature/Scenarios/PaymentMatchingScenarioTest.php` extending `MemberLifecycleScenario` containing the required minimum flow as one `test_*` method.
- Suggested follow-up test cases (separate `test_*` methods in the same class or a sibling scenario class) with sketches in this plan.

## Test Support Helpers

### Tests\Concerns\TestsBankingLifecycle

New file `tests/Concerns/TestsBankingLifecycle.php`, sibling of `TestsMemberLifecycle` and `WithAuthorizedUser`, following their conventions (strict types, typed helpers, PHPDoc array shapes).

```php
<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Filament\Admin\Resources\BankTransactions\Pages\ViewBankTransaction;
use App\Filament\Admin\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Filament\Admin\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CostCenter;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

trait TestsBankingLifecycle
{
    /**
     * Create a purchase order through the admin create page and return it.
     *
     * @param array<string, mixed> $overrides
     */
    protected function createPurchaseOrder(array $overrides = []): PurchaseOrder
    {
        $data = array_merge([
            'creditor_name' => 'Sloepenhandel BV',
            'creditor_iban' => 'NL02INGB0002447973',
            'description' => 'Inkoop bootmateriaal',
            'date' => now()->format('Y-m-d'),
            'image_path' => UploadedFile::fake()->image('factuur.jpg'),
            'lines' => [
                [
                    'description' => 'Vlot',
                    'price' => 150.00,
                    'price_vat' => 31.50,
                    'cost_center_id' => CostCenter::query()->firstOrFail()->id,
                ],
            ],
        ], $overrides);

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm($data)
            ->call('create')
            ->assertHasNoFormErrors();

        return PurchaseOrder::query()
            ->where('description', $data['description'])
            ->firstOrFail();
    }

    protected function approvePurchaseOrder(PurchaseOrder $purchaseOrder): void
    {
        Livewire::test(EditPurchaseOrder::class, ['record' => $purchaseOrder->getRouteKey()])
            ->callAction('markAsApproved');
    }

    protected function markInvoiceAsPending(Invoice $invoice): void
    {
        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->callAction('markAsPending');
    }

    /**
     * Build the contents of a single-statement MT940 file (Rabo format).
     *
     * @param list<array{date: string, debitCredit: 'C'|'D', amount: float, counterpartyIban: string, description: string}> $transactions
     */
    protected function mt940Statement(BankAccount $bankAccount, array $transactions, float $openingBalance = 1000.00): string
    {
        $statementDate = CarbonImmutable::parse($transactions[0]['date'])->format('ymd');

        $lines = [
            ':940:',
            ':20:WSV-SCENARIO-001',
            sprintf(':25:%s', $bankAccount->iban),
            ':28C:1/1',
            sprintf(':60F:C%sEUR%s', $statementDate, $this->formatMt940Amount($openingBalance)),
        ];

        foreach ($transactions as $index => $transaction) {
            $lines[] = sprintf(
                ':61:%s%s%sNMSC//%03d',
                CarbonImmutable::parse($transaction['date'])->format('ymd'),
                $transaction['debitCredit'],
                $this->formatMt940Amount($transaction['amount']),
                $index + 1,
            );
            // The line below :61: is parsed as the counterparty account (Rabo engine).
            $lines[] = $transaction['counterpartyIban'];
            $lines[] = sprintf(':86:/NAME/Scenario/REMI/%s', $transaction['description']);
        }

        $closingBalance = $openingBalance;
        foreach ($transactions as $transaction) {
            $closingBalance += $transaction['debitCredit'] === 'C' ? $transaction['amount'] : -$transaction['amount'];
        }

        $lines[] = sprintf(':62F:C%sEUR%s', $statementDate, $this->formatMt940Amount($closingBalance));
        $lines[] = '-';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Import an MT940 statement through the admin import action.
     * The action dispatches MatchBankTransactionsJob, which runs inline (sync queue).
     */
    protected function importMt940Statement(string $content): void
    {
        Livewire::test(ListBankTransactions::class)
            ->callAction('importMt940', data: [
                'mt940_file' => UploadedFile::fake()->createWithContent('statement.mta', $content),
            ])
            ->assertHasNoActionErrors();
    }

    protected function completeTransaction(BankTransaction $transaction): void
    {
        Livewire::test(ViewBankTransaction::class, ['record' => $transaction->getRouteKey()])
            ->callAction('complete');
    }

    private function formatMt940Amount(float $amount): string
    {
        return str_replace('.', ',', sprintf('%.2f', $amount));
    }
}
```

Notes on this helper:

- `importMt940Statement` deliberately drives the real `ImportMt940Action` (`app/Filament/Admin/Actions/ImportMt940Action.php`) so the scenario covers the full admin flow including the `MatchBankTransactionsJob::dispatch()` (sync queue in `phpunit.xml` runs it before the call returns). Do not wrap storage in `Storage::fake('local')` — the action reads the file back via `storage_path('app/private/…')`, which a faked disk would break.
- The MT940 output format is modelled on `tests/Fixtures/mt940/rabo.mta` (first line `:940:` selects the Rabo engine; contra-account line under `:61:` becomes `banking_account_number`; `/REMI/…` becomes the description). Keep `:61:` in the exact shape `YYMMDD` + `C|D` + zero-padded amount with comma + `N` + 3-char code (regexes in the parser require digits right after `C|D` and an `N` after the amount).
- The statement balances must be consistent (closing = opening + Σ signed amounts) so no integrity warning is raised in the happy path.

## Scenario Test

### tests/Feature/Scenarios/PaymentMatchingScenarioTest.php

New file, `final class`, extends `MemberLifecycleScenario`, uses `TestsBankingLifecycle`, `#[Override]` on `setUp` — mirroring `WindsurferLifetimeScenarioTest` conventions (commented story steps, `static::assert*`, `assertDatabaseHas`).

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\BankTransactions\ResolveStatus;
use App\Domain\Invoices\InvoiceStatus;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Override;
use Tests\Concerns\TestsBankingLifecycle;

final class PaymentMatchingScenarioTest extends MemberLifecycleScenario
{
    use TestsBankingLifecycle;

    private const string MEMBER_IBAN = 'NL91ABNA0417164300';
    private const string CREDITOR_IBAN = 'NL02INGB0002447973';

    #[Override]
    public function setUp(): void
    {
        parent::setUp();

        $this->travelToDate('2026-08-15');
    }

    public function test_an_invoice_and_purchase_order_are_settled_by_imported_transactions(): void
    {
        // Step 1 — the member registers through the public wizard (IBAN is the payment default).
        $member = $this->registerMember();

        $this->assertDatabaseHas('payment_information', [
            'member_id' => $member->id,
            'banking_account_number' => self::MEMBER_IBAN,
        ]);

        // Step 2 — the invoice is generated and marked pending (which books it).
        $this->generateInvoiceFor($member);

        $invoice = $this->invoiceFor($member, '2026-08-15');
        $this->assertInvoiceLine($invoice, 'Lidmaatschap Windsurfer (volwassenen)', 68.00, 0.42);
        $this->assertInvoiceLine($invoice, 'Vrijwilligersbijdrage', 20.00, 0.42);
        static::assertEqualsWithDelta(36.96, $invoice->total->price, 0.001);

        $this->markInvoiceAsPending($invoice);
        static::assertSame(InvoiceStatus::Pending, $invoice->refresh()->status);
        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);

        // Step 3 — a purchase order is created and approved (approved == pending).
        $purchaseOrder = $this->createPurchaseOrder([
            'creditor_name' => 'Sloepenhandel BV',
            'creditor_iban' => self::CREDITOR_IBAN,
            'date' => '2026-08-15',
        ]);

        $this->approvePurchaseOrder($purchaseOrder);
        static::assertSame(PurchaseOrderStatus::Pending, $purchaseOrder->refresh()->status);
        static::assertEqualsWithDelta(150.00, $purchaseOrder->total->price, 0.001);

        // Step 4 — the bank statement arrives: the member pays the invoice, the club pays the purchase order.
        $this->travelToDate('2026-08-20');
        $bankAccount = BankAccount::factory()->create();

        $this->importMt940Statement($this->mt940Statement($bankAccount, [
            [
                'date' => '2026-08-20',
                'debitCredit' => 'C',
                'amount' => 36.96,
                'counterpartyIban' => self::MEMBER_IBAN,
                'description' => 'Lidmaatschap augustus',
            ],
            [
                'date' => '2026-08-20',
                'debitCredit' => 'D',
                'amount' => 150.00,
                'counterpartyIban' => self::CREDITOR_IBAN,
                'description' => 'Inkoop bootmateriaal',
            ],
        ]));

        static::assertSame(2, BankTransaction::query()->count());
        $this->assertDatabaseHas('bank_statements', ['iban' => $bankAccount->iban]);

        // Step 5 — matching linked both transactions automatically, without settling anything yet.
        $creditTransaction = BankTransaction::query()->where('amount', '>', 0)->sole();
        $debitTransaction = BankTransaction::query()->where('amount', '<', 0)->sole();

        $this->assertDatabaseHas('bank_transaction_references', [
            'bank_transaction_id' => $creditTransaction->id,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);
        $this->assertDatabaseHas('bank_transaction_references', [
            'bank_transaction_id' => $debitTransaction->id,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->id,
        ]);

        static::assertSame(ResolveStatus::Resolved, $creditTransaction->refresh()->resolve_status);
        static::assertSame(ResolveStatus::Resolved, $debitTransaction->refresh()->resolve_status);
        static::assertSame(InvoiceStatus::Pending, $invoice->refresh()->status);
        static::assertSame(PurchaseOrderStatus::Pending, $purchaseOrder->refresh()->status);
        static::assertEqualsWithDelta(0.0, $creditTransaction->unmatched_amount, 0.001);
        static::assertEqualsWithDelta(0.0, $debitTransaction->unmatched_amount, 0.001);

        // Step 6 — completing the member's payment settles the invoice.
        $this->completeTransaction($creditTransaction);

        static::assertSame(BankTransactionStatus::Completed, $creditTransaction->refresh()->status);
        static::assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'bank_transaction_id' => $creditTransaction->id,
        ]);

        // Step 7 — completing the outgoing payment settles the purchase order.
        $this->completeTransaction($debitTransaction);

        static::assertSame(BankTransactionStatus::Completed, $debitTransaction->refresh()->status);
        static::assertSame(PurchaseOrderStatus::Paid, $purchaseOrder->refresh()->status);
        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->id,
            'bank_transaction_id' => $debitTransaction->id,
        ]);
    }
}
```

Important invariants baked into this test (all verified against the current code):

- The registered member's `payment_information.banking_account_number` is `NL91ABNA0417164300` (the default in `TestsMemberLifecycle::registerMember`). The credit transaction's counterparty must be that exact string — `findMatchingCredit` compares it without normalization.
- The transaction amount must equal `Invoice::total->price` / `PurchaseOrder::total->price` (both ex-VAT sums) within `0.01`, otherwise the match is rejected. The invoice for a member registering on 2026-08-15 with the default Windsurfer membership totals `68.00 × 0.42 + 20.00 × 0.42 = 36.96` (`quantity` is `decimal:2`, so 5/12 is stored as `0.42`).
- Both transactions must be dated within ±30 days of the invoice/PO date (2026-08-15). 2026-08-20 works.
- The own `BankAccount` (`:25:`) must differ from the member IBAN and the creditor IBAN — a counterparty equal to one of our own accounts is routed to internal-transfer matching instead. `BankAccount::factory()` generates a random NL IBAN and cannot collide.
- Marking the PO "pending" is the `markAsApproved` action; it sets `PurchaseOrderStatus::Pending` and requires a creditor name, creditor IBAN and a cost center on every line (the `CostCenterSeeder` cost centers satisfy this).
- Completing the debit transaction marks the PO paid only because `BankTransactionDbRepository::complete` sets the transaction to `Completed` before `PurchaseOrderService::markAsPaid` checks `hasCompletedTransactions`.
- `bookkeeping_records` rows created when marking the invoice/PO pending have `reference_id` set, so they are excluded from `matched_amount`'s `unattached()` term and never double count.

## Suggested Additional Test Cases

Separate `test_*` methods, self-contained (each builds its own invoice/PO/transactions via the same helpers). Recommended as a second wave after the main scenario passes; they may live in `PaymentMatchingScenarioTest` or a sibling class such as `PaymentMatchingEdgeCasesScenarioTest` in the same directory.

### Re-import is idempotent

`test_reimporting_the_same_statement_only_skips_duplicates` — import the same statement twice via `importMt940Statement`; assert `BankTransaction::query()->count()` is still 2 (the `import_hash` unique key deduplicates; `importFromFile` returns `skipped = 2` on the second run — assert via `app(BankTransactionImportService::class)->importFromFile(...)` on a temp file if the exact counts matter).

### Unmatched payment cannot be completed

`test_a_payment_with_the_wrong_amount_stays_unresolved_and_cannot_be_completed` — import a credit of 35.00 against the 36.96 invoice. After matching: `resolve_status = Unresolvable`, no `bank_transaction_references` rows, `unmatched_amount = 35.00`. Assert the UI guard: `Livewire::test(ViewBankTransaction::class, ['record' => $transaction->getRouteKey()])->assertActionDisabled('complete');` and the domain guard:

```php
$this->expectException(CouldNotCompleteTransaction::class);

app(BankTransactionService::class)->complete(BankTransactionId::create($transaction->id));
```

### Reversal declines the documents

`test_a_reversed_payment_declines_the_invoice_and_purchase_order` — get both documents matched (steps 1–5 of the main scenario, without completing). Import a second statement containing the mirror transactions: same counterparty, same `/REMI/` description (reversal matching requires an identical description), opposite `C|D`, dated on/after the originals (within 56 days). After the inline matching job both originals are linked as reversals (`reversed_by_transaction_id` set), the invoice flips `Pending → Declined`, the PO flips `Pending → Declined`, and all four transactions are `Resolved`. Then `unlinkReversal` via `Livewire::test(ViewBankTransaction::class, [...])->callAction('unlinkReversal')` restores the invoice to `Pending` and the PO to `Pending` (re-approved).

### Internal transfer completes automatically

`test_an_internal_transfer_between_own_accounts_is_linked_and_completed` — create two `BankAccount`s; one statement per account, each with one transaction whose counterparty is the other account's IBAN, opposite amounts. After the import + matching both rows are linked (`bank_transaction_links`, `link_type = internal_transfer`) and are already `Completed` + `Resolved` (this is the one flow that completes transactions during matching).

### Retry matching after late approval

`test_matching_can_be_retried_after_the_invoice_is_marked_pending` — import the payment while the invoice is still `Open`; matching marks the transaction `Unresolvable`. Then `markInvoiceAsPending($invoice)` and re-run matching via `Livewire::test(ViewBankTransaction::class, [...])->callAction('retryMatching')`; assert the transaction is now `Resolved` and attached to the invoice.

### Matching window

`test_a_transaction_outside_the_thiry_day_window_is_not_matched` — invoice dated 2026-08-15, transaction value date 2026-09-20 (> 30 days) with otherwise perfect amount/IBAN; assert `Unresolvable` and no references.

### Closest amount wins

`test_matching_picks_the_closest_amount` — two `Pending` invoices for the same member in the window with different totals; the transaction attaches to the one whose total it equals (guards `orderByAmountProximity` plus the `0.01` guard).

### Statement integrity warning

`test_a_statement_with_an_unbalanced_closing_amount_reports_an_integrity_warning` — build the statement with a closing balance that is 10.00 off; assert the import result `integrity_warnings = 1` (via `app(BankTransactionImportService::class)->importFromFile()` on a written file) and `bank_statements.integrity_status` is `Mismatch`.

### Complete-all on a statement

`test_all_matched_transactions_on_a_statement_can_be_completed_at_once` — use `App\Filament\Admin\Resources\BankStatements\Actions\CompleteAllMatchedAction` (action `completeAllMatched`) on the statement's page instead of per-transaction completion; assert both documents end up `Paid`.

## Verification

Run the new scenario file:

```
./Taskfile artisan test --compact tests/Feature/Scenarios/PaymentMatchingScenarioTest.php
```

Then the neighbouring scenario and the touched domain areas:

```
./Taskfile artisan test --compact tests/Feature/Scenarios/
./Taskfile artisan test --compact --filter=BankTransaction
```

If any test is updated afterwards, re-run that single test. Before finishing, ask the user whether to run the entire suite (`./Taskfile artisan test --compact`).
