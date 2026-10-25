# SEPA mandate lifecycle — implementation plan

Date: 2026-10-22
Backlog step: 4 of 13 in [Prioritize the confirmed backlog and decide the plan split](../maps/20261021-missing-features/tickets/07-prioritize-confirmed-backlog-and-decide-plan-split.md)
Decisions source: [Judge SEPA & payment robustness candidates](../maps/20261021-missing-features/tickets/04-judge-sepa-payment-robustness-candidates.md), gap 1

## Overview

Turn the write-once `payment_information` row into a first-class, versioned SEPA mandate. A member has many mandates over
time and at most one active one. Each carries its own IBAN/BIC/account holder, signed date, `revoked_at`,
`last_collected_at` and its derived reference (`C000042-000007`). Existing rows evolve in place with their ids intact, so
no mandate that has already been collected changes identity.

Re-authorisation is a new mandate: the old row is revoked (keeping its IBAN for refunds) and the new row gets a new
reference, which makes its first collection `FRST` again (plan 05). Revocation alone makes the member a manual payer —
there is no stored payment-method column. Expiry is derived, not stored: 36 months without a collection, counted from
submission, so a collection that is presented and later declined still counts as use. Expired mandates are surfaced on
the member and in the export readiness report, and can be collected anyway through plan 02's per-export override with a
reason.

The admin panel gains a mandate relation manager (create/edit/revoke), the mandate reference and state become visible,
the dead `uuid` "reference" field on the member payment tab is removed, and the broken insert path for a member without
payment information is replaced by a real create action.

## Current situation

- `database/migrations/2026_06_10_181456_create_payment_information_table.php` defines one row per member:
  `member_id` is **unique** (line 14), `mandate_accepted_date` is **NOT NULL** (line 18), and the table is
  **soft-deletable** (line 20). A second mandate for the same member is impossible; a soft delete is the only "remove"
  operation and it also hides the IBAN needed for refunds.
- `app/Models/PaymentInformation.php` is a thin model (member relation, `mandate_accepted_date` cast). It has no state,
  no last-used timestamp and no reference accessor, and it uses `SoftDeletes`.
- `app/Models/Member.php:111-115` exposes a single `paymentInformation()` has-one. Every consumer assumes "the" mandate:
  - `app/Infrastructure/Billing/Payment/SepaBatchRepositoryDb.php:112` inner-joins `member.paymentInformation` for the
    export. With several rows this would duplicate invoice rows (one per mandate) or pick an arbitrary row.
  - `app/Infrastructure/Billing/Invoices/InvoiceMailRepositoryDb.php:24,40,45` uses the row to decide SEPA vs transfer
    text and to build the refund IBAN line.
  - `app/Infrastructure/Billing/Invoices/InvoiceRepositoryDb.php:207` matches an incoming credit on
    `member.paymentInformation.banking_account_number`, i.e. only the current mandate.
  - `app/Filament/Admin/Resources/PurchaseOrders/Helpers/MemberCreditorPrefill.php:23` reads
    `$member->paymentInformation->banking_account_number` without a null guard.
  - `app/Filament/Admin/Resources/BankTransactions/Actions/AttachBankTransactionAction.php:40` uses the same relation
    to rank candidate transactions.
- The mandate reference is derived, never stored: `app/Domain/Billing/Payment/MandateId.php:15` formats
  `C{memberId:06d}-{paymentInformationId:06d}`. It is emitted into the XML but shown nowhere in the panel.
- The admin member form (`app/Filament/Admin/Resources/Members/Schemas/MemberForm.php:136-170`) renders the payment tab
  as `Grid::make()->relationship('paymentInformation')` (lines 138-139). Filament auto-creates a related row from
  dehydrated fields,
  but `mandate_accepted_date` is `disabled()->dehydrated(false)` (lines 157-162), so creating a mandate for a member
  without payment information violates the not-null column. The tab also renders `TextInput::make('uuid')`
  (lines 164-167) — there is no such column; it is the dead pre-`MandateId` reference field.
- `app/Infrastructure/Billing/Payment/SepaExportServiceImpl.php:89-90` passes `mandateId` and `mandateDate` per collection;
  `SepaExportInvoice` (`app/Domain/Billing/Payment/SepaExportInvoice.php:11-22`) therefore happens to be the only
  carrier of the mandate identity into the export. It does not expose the underlying row id or last use.
- `app/Infrastructure/Members/MemberDbRepository.php:64-71` creates the mandate during registration. That write path is
  the only place mandates are created today; `app/Http/Requests/Registration/StorePaymentInformationRequest.php` and
  `app/Domain/Members/NewMemberService.php` are fine and stay unchanged.
- There is no `revoked_at`, no `last_collected_at`, no expiry and no state anywhere (grep for
  `expire|expiry|reauthor|last_collected|36 months` returns nothing in `app/`).
- Permissions already exist and are granted to the financial administration only:
  `view_member_payment_information` / `update_member_payment_information`
  (`app/Domain/Authorization/ResourcePermission.php:19-20`, `database/seeders/RolePermissionSeeder.php:95`),
  gated through `app/Policies/MemberPolicy.php:35-43`. Member users can view their own row through the same policy, so
  the mandate *management* surface must explicitly exclude them.

## Planned changes

| Change | Main files |
|---|---|
| Evolve `payment_information` in place: drop one-per-member unique, add lifecycle columns, retire soft deletes | three migrations, `MandateLifecycleBackfill` |
| Add mandate state and 36-month expiry rule | `MandateState`, `MandateExpiry` |
| Expose the active mandate and the history on `Member`; add the derived reference and state to the model | `Member`, `PaymentInformation` |
| Add a mandate repository with the re-authorisation invariant | `MandateRepository`, `MandateDetails`, `MandateCorrection`, `PaymentInformationIdList`, `MandateRepositoryDb` |
| Export rows: active mandate for collections, latest mandate (including revoked) for refunds | `SepaBatchRepositoryDb`, `SepaExportInvoice` |
| Stamp `last_collected_at` when a batch is submitted | plan 02's submission path, `SepaBatchServiceImpl`, `MandateRepositoryDb` |
| Readiness: make the mandate seam return Active/Expired/Revoked; expired stays overridable | `MandateReadinessServiceImpl` |
| Invoice mail resolves the active mandate; declined notice flags revoked/expired | `InvoiceMailRepositoryDb` |
| Incoming credits still match the IBAN of a revoked mandate | `InvoiceRepositoryDb::findMatchingCredit` |
| Admin create/edit/revoke mandate for a member without payment information | `MandatesRelationManager`, `MemberResource`, `MemberForm`, `MembersTable` |
| Keep finance/admin-only access, remove the dead `uuid` field, add Dutch labels | `MembersTable`, `MemberForm`, `lang/nl/*` |

## Evolve `payment_information` in place

**Decision.** Keep the table and the row ids. A new `mandates` table is rejected because:

- `MandateId` derives the reference from `payment_information.id` (`MandateId.php:15`). Every pain.008 file already sent
  carries `C{member}-{row id}`. Keeping ids keeps every already-collected mandate's reference identical; a new table
  would require copying ids or re-issuing references.
- `payment_information` is the stem of the permission names (`view_member_payment_information`), the label key and every
  `paymentInformation` relation read above. A rename would ripple through role configuration and every other plan for no
  behavioural gain.
- Nothing in the schema prevents versioned rows except the unique index. Dropping it is a one-line DDL change; the
  concept that was missing is state, not storage.

The table keeps its historical name; the row **is** a mandate. `MandateRepository` and `MandateState` are therefore the
domain vocabulary for the lifecycle, while `PaymentInformation` remains the Eloquent row/model name.

**Invariants.**

- **Active** means `revoked_at IS NULL`. A member has at most one active mandate, enforced by a partial unique index and
  by a locking transaction on every write.
- **Expired** is derived and is *not* a revocation. An expired mandate is still the active mandate: it may be collected
  through the readiness override, and creating a new mandate revokes it like any other active mandate.
- **Revoked** wins over expired. Revocation keeps every column, including the IBAN; the row stays in the member's
  history.
- **Soft deletes are retired.** Two ways to hide a row would make "active" ambiguous. Existing soft-deleted rows are
  converted to revoked mandates (their `deleted_at` becomes `revoked_at`) and the column is dropped. No code uses
  `withTrashed`/`onlyTrashed` on this table (verified by grep), so nothing else changes behaviour.

### Lifecycle columns migration

`database/migrations/2026_10_22_110000_add_mandate_lifecycle_to_payment_information.php`:

```php
return new class() extends Migration {
    public function up(): void
    {
        Schema::table('payment_information', static function (Blueprint $table): void {
            $table->dropUnique(['member_id']);
            $table->index('member_id');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_collected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_information', static function (Blueprint $table): void {
            $table->dropColumn(['revoked_at', 'last_collected_at']);
            $table->dropIndex(['member_id']);
            $table->unique('member_id');
        });
    }
};
```

`$table->dropUnique(['member_id'])` targets the existing `payment_information_member_id_unique` index. The plain index
replaces it for lookups; uniqueness is narrowed to active rows by the active-mandate migration.

### Backfill migration

Put the DML in a small reusable class so it can be tested against a legacy-shaped table:

`app/Infrastructure/Billing/Payment/MandateLifecycleBackfill.php`:

```php
final readonly class MandateLifecycleBackfill
{
    public function __construct(
        private string $table = 'payment_information',
    ) {}

    public function backfillRevocations(): int
    {
        return DB::table($this->table)
            ->whereNotNull('deleted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => DB::raw('deleted_at')]);
    }

    public function backfillLastCollectedAt(): int
    {
        $updated = 0;

        $collections = DB::table('invoices')
            ->join('sepa_batches', 'sepa_batches.id', '=', 'invoices.sepa_batch_id')
            ->whereNotNull('invoices.sepa_batch_id')
            ->whereNull('invoices.credit_invoice_id')
            ->where(static function (Builder $query): void {
                $query->where('sepa_batches.status', 'completed')
                    ->orWhereNotNull('sepa_batches.exported_at');
            })
            ->whereExists(static function (Builder $query): void {
                $query->select(DB::raw('1'))
                    ->from('invoice_lines')
                    ->whereColumn('invoice_lines.invoice_id', 'invoices.id')
                    ->groupBy('invoice_lines.invoice_id')
                    ->havingRaw('SUM(invoice_lines.price * invoice_lines.quantity) > 0');
            })
            ->groupBy('invoices.member_id')
            ->select('invoices.member_id', DB::raw('MAX(sepa_batches.sepa_transfer_date) as collected_at'))
            ->get();

        foreach ($collections as $collection) {
            $updated += DB::table($this->table)
                ->where('member_id', $collection->member_id)
                ->whereNull('revoked_at')
                ->whereNull('last_collected_at')
                ->update(['last_collected_at' => $collection->collected_at]);
        }

        return $updated;
    }
}
```

`database/migrations/2026_10_22_110001_backfill_mandate_lifecycle.php`:

```php
return new class() extends Migration {
    public function up(): void
    {
        $backfill = new MandateLifecycleBackfill();
        $backfill->backfillRevocations();
        $backfill->backfillLastCollectedAt();
    }

    public function down(): void
    {
        DB::table('payment_information')->update([
            'revoked_at' => null,
            'last_collected_at' => null,
        ]);
    }
};
```

Why this backfill:

- A soft-deleted row is a mandate that was withdrawn before revocation existed. Converting `deleted_at` to `revoked_at`
  preserves both the date and the IBAN.
- `last_collected_at` is reconstructed from batches that were demonstrably exported: `status = completed` covers the
  pre-plan-02 history, `exported_at IS NOT NULL` covers plan 02 onward. The rule is "presented counts as use", so a
  positive, non-credit invoice in such a batch stamps the member's (single, pre-versioning) mandate. A refund-only
  batch never starts the clock. Paid status alone is *not*
  used: a manual transfer also marks an invoice Paid and would falsely restart the 36-month clock.
- Without this backfill, plan 05 would treat every existing mandate as never collected and emit `FRST` for the whole
  membership in the first batch. The backfill keeps established mandates on `RCUR`.
- A legacy batch that was exported but never marked `Completed` leaves no evidence and stays `null`, so its mandate would
  be treated as never collected once. That is the safe direction: `FRST` on an existing mandate is tolerated by the
  debtor bank (it triggers mandate verification), whereas `RCUR` on a genuine first collection is the scheme violation
  plan 05 exists to prevent.
- Plan 02's export-tracking migration runs before this one (`100000` < `110000`), so `sepa_batches.exported_at` exists.
- No row id is rewritten. Because `MandateId` derives the reference from `(member_id, payment_information.id)`, the
  migration cannot change an existing mandate's reference; the reference test below locks that in.

### Active-mandate index and soft-delete retirement

`database/migrations/2026_10_22_110002_enforce_one_active_mandate_per_member.php`:

```php
return new class() extends Migration {
    public function up(): void
    {
        Schema::table('payment_information', static function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        DB::statement(
            'CREATE UNIQUE INDEX payment_information_one_active_mandate_per_member
             ON payment_information (member_id)
             WHERE revoked_at IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payment_information_one_active_mandate_per_member');

        Schema::table('payment_information', static function (Blueprint $table): void {
            $table->softDeletes();
        });
    }
};
```

The partial index is a raw statement because Laravel's `Blueprint` has no partial-index API. Both configured databases
support it: production is `pgsql` and the test suite runs `sqlite` (`phpunit.xml`). MySQL is not used by this project.

**Rollback note.** The migrations are forward-fix migrations. Rolling back after versioned rows exist cannot restore the
old unique constraint without data loss (several rows per member), and cannot reproduce the soft-delete history. That is
deliberate and documented in the plan, not silently ignored.

## Mandate state and the 36-month rule

`app/Domain/Billing/Payment/MandateState.php`:

```php
enum MandateState: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
```

`app/Domain/Billing/Payment/MandateExpiry.php`:

```php
final readonly class MandateExpiry
{
    public const int MONTHS_WITHOUT_COLLECTION = 36;

    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function expiresAt(DateTimeInterface $signedAt, ?DateTimeInterface $lastCollectedAt): DateTimeInterface
    {
        return $this->addMonthsWithoutOverflow(
            $lastCollectedAt ?? $signedAt,
            self::MONTHS_WITHOUT_COLLECTION,
        );
    }

    public function state(
        DateTimeInterface $signedAt,
        ?DateTimeInterface $lastCollectedAt,
        ?DateTimeInterface $revokedAt,
    ): MandateState {
        if ($revokedAt !== null) {
            return MandateState::Revoked;
        }

        $expiresAt = $this->expiresAt($signedAt, $lastCollectedAt);

        if ($this->clock->now()->format('Y-m-d') >= $expiresAt->format('Y-m-d')) {
            return MandateState::Expired;
        }

        return MandateState::Active;
    }

    private function addMonthsWithoutOverflow(DateTimeInterface $date, int $months): DateTimeInterface
    {
        $immutable = DateTimeImmutable::createFromInterface($date);
        $firstOfTargetMonth = $immutable->setDate(
            (int) $immutable->format('Y'),
            (int) $immutable->format('n') + $months,
            1,
        );

        return $firstOfTargetMonth->setDate(
            (int) $firstOfTargetMonth->format('Y'),
            (int) $firstOfTargetMonth->format('n'),
            min((int) $immutable->format('j'), (int) $firstOfTargetMonth->format('t')),
        );
    }
}
```

Semantics:

- The clock starts at `last_collected_at` when it exists, otherwise at the signed date. A mandate that has never been
  used expires 36 months after signing.
- The boundary is inclusive on the day: 35 months → Active, exactly 36 months → Expired, 37 → Expired. Finance sees the
  warning before the next monthly run, not after it.
- `last_collected_at` is stamped at **submission** (see below), not at payment success. A presented-then-declined
  collection therefore counts as use, and plan 01's decline path must never clear it.
- Month arithmetic clamps to the last day (`31 Jan + 36 months → 31 Jan`, `29 Feb → 28 Feb`), so the boundary never
  silently moves.
- The clock is injected (`psr/clock`, bound to `Carbon\FactoryImmutable` in `AppServiceProvider`) and unit tests use the
  existing `ClockExpectation`.

## Mandate model, relations and reference

`app/Models/PaymentInformation.php`:

```php
/**
 * A SEPA mandate: one row per signed authorisation. A member has many over
 * time and at most one active row (revoked_at is null).
 *
 * @property int $id
 * @property int $member_id
 * @property string $banking_account_number
 * @property string $banking_bic
 * @property string $banking_account_holder_name
 * @property DateTimeInterface $mandate_accepted_date
 * @property null|DateTimeInterface $revoked_at
 * @property null|DateTimeInterface $last_collected_at
 */
#[Guarded('id', 'updated_at', 'created_at')]
final class PaymentInformation extends Model
{
    use HasFactory;

    #[Override]
    protected $table = 'payment_information';

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return Attribute<string, never> */
    protected function mandateReference(): Attribute
    {
        return Attribute::get(fn (): string => new MandateId(
            MemberId::create($this->member_id),
            PaymentInformationId::create($this->id),
        )->value);
    }

    public function state(MandateExpiry $expiry): MandateState
    {
        return $expiry->state(
            signedAt: $this->mandate_accepted_date,
            lastCollectedAt: $this->last_collected_at,
            revokedAt: $this->revoked_at,
        );
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'mandate_accepted_date' => 'date',
            'revoked_at' => 'datetime',
            'last_collected_at' => 'datetime',
        ];
    }
}
```

`SoftDeletes` is removed from the class; the column is gone after the active-mandate migration.

`app/Models/Member.php`:

```php
/** The member's current (non-revoked) mandate. @return HasOne<PaymentInformation, $this> */
public function paymentInformation(): HasOne
{
    return $this->hasOne(PaymentInformation::class)->whereNull('revoked_at');
}

/** The full mandate history, newest row first. @return HasMany<PaymentInformation, $this> */
public function paymentInformations(): HasMany
{
    return $this->hasMany(PaymentInformation::class)->orderByDesc('id');
}
```

Ordering by `id` (creation order) rather than by signed date matters: a backdated re-authorisation still has to be the
"latest" row, because it is the one that revoked the previous mandate and is active. The relation manager's table
sorts by signed date for display, but `paymentInformations->first()` always means "the most recently created row".

`paymentInformation` deliberately stays the name of the *active* relation: every existing consumer (mail, export,
matching suggestions) means "the mandate we may collect on", and keeping the name keeps those call sites honest. The
history gets the plural relation, used by the relation manager.

The Billing context glossary (`.agents/docs/billing/CONTEXT.md`) gains a **Mandate** entry: one signed authorisation, a
member has many and exactly one active, identity is the derived `MandateId`, expiry is derived.

## Mandate repository and write path

Domain seam (`app/Domain/Billing/Payment/MandateRepository.php`):

```php
#[Autowire]
interface MandateRepository
{
    public function register(MemberId $memberId, MandateDetails $details): PaymentInformationId;

    public function correctDetails(PaymentInformationId $id, MandateCorrection $details): void;

    public function revoke(PaymentInformationId $id, DateTimeInterface $revokedAt): void;

    public function stampCollections(PaymentInformationIdList $ids, DateTimeInterface $collectedAt): int;
}
```

`app/Domain/Billing/Payment/MandateDetails.php`:

```php
final readonly class MandateDetails
{
    public function __construct(
        public string $iban,
        public string $bic,
        public string $accountHolderName,
        public DateTimeInterface $signedAt,
    ) {}
}
```

`app/Domain/Billing/Payment/MandateCorrection.php` — the editable subset of an existing mandate. IBAN and signed date
are deliberately absent: they identify the signed authorisation. Changing either is a re-authorisation, which goes
through `register()` and produces a new mandate, a new reference and a first collection again:

```php
final readonly class MandateCorrection
{
    public function __construct(
        public string $bic,
        public string $accountHolderName,
    ) {}
}
```

`app/Domain/Billing/Payment/PaymentInformationIdList.php` mirrors `InvoiceIdList`:

```php
final class PaymentInformationIdList
{
    /** @param PaymentInformationId[] $ids */
    public function __construct(
        public array $ids,
    ) {
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        Assert::allIsInstanceOf($ids, PaymentInformationId::class);
    }

    /** @param int[] $array */
    public static function fromArray(array $array): self
    {
        return new self(array_map(PaymentInformationId::create(...), $array));
    }

    /** @return int[] */
    public function values(): array
    {
        return array_map(static fn (PaymentInformationId $id): int => $id->value, $this->ids);
    }
}
```

`app/Infrastructure/Billing/Payment/MandateRepositoryDb.php`:

```php
final class MandateRepositoryDb implements MandateRepository
{
    #[Override]
    public function register(MemberId $memberId, MandateDetails $details): PaymentInformationId
    {
        return DB::transaction(function () use ($memberId, $details): PaymentInformationId {
            Member::query()
                ->whereKey($memberId->value)
                ->lockForUpdate()
                ->firstOrFail();

            PaymentInformation::query()
                ->where('member_id', $memberId->value)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => CarbonImmutable::now()]);

            $mandate = PaymentInformation::query()->create([
                'member_id' => $memberId->value,
                'banking_account_number' => $details->iban,
                'banking_bic' => $details->bic,
                'banking_account_holder_name' => $details->accountHolderName,
                'mandate_accepted_date' => $details->signedAt,
                'revoked_at' => null,
                'last_collected_at' => null,
            ]);

            return PaymentInformationId::create($mandate->id);
        });
    }

    #[Override]
    public function correctDetails(PaymentInformationId $id, MandateCorrection $details): void
    {
        PaymentInformation::query()
            ->whereKey($id->value)
            ->whereNull('revoked_at')
            ->update([
                'banking_bic' => $details->bic,
                'banking_account_holder_name' => $details->accountHolderName,
            ]);
    }

    #[Override]
    public function revoke(PaymentInformationId $id, DateTimeInterface $revokedAt): void
    {
        PaymentInformation::query()
            ->whereKey($id->value)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]);
    }

    #[Override]
    public function stampCollections(PaymentInformationIdList $ids, DateTimeInterface $collectedAt): int
    {
        if ($ids->ids === []) {
            return 0;
        }

        return PaymentInformation::query()
            ->whereIn('id', $ids->values())
            ->where(static function (Builder $query) use ($collectedAt): void {
                $query->whereNull('last_collected_at')
                    ->orWhere('last_collected_at', '<', $collectedAt);
            })
            ->update(['last_collected_at' => $collectedAt]);
    }
}
```

Notes:

- `register` is both "create the first mandate" and "re-authorise": it revokes the current active row and inserts the new
  one in one transaction, after locking the member. The partial unique index is the second line of defence.
- The old row keeps its IBAN, BIC, holder and signed date. Only `revoked_at` changes; its derived reference is untouched.
- `correctDetails` and `revoke` filter on `revoked_at IS NULL`, so a stale UI cannot rewrite history. Both are idempotent
  no-ops for a revoked row instead of throwing, because the UI already hides the actions and the races are benign.
- `correctDetails` deliberately cannot change the IBAN or the signed date. Those two fields identify the signed
  authorisation and its account; editing them in place would silently turn an existing mandate into a different one
  while keeping its reference and RCUR sequence. A changed IBAN or signed date is a re-authorisation and must use
  `register()`, which revokes the old row, issues a new reference and starts the new mandate at FRST.
- `stampCollections` only moves `last_collected_at` forward, so replaying a submission or processing an older batch can
  never rewind the 36-month clock.
- Timestamps use `now()` in infrastructure (consistent with `InvoiceRepositoryDb` and `BankTransactionDbRepository`);
  feature tests use `travelTo`. The expiry *rule* itself remains clock-injected in the domain.

## Registration write path

`MemberDbRepository::newMember` keeps creating the row through the (now active-constrained) relation at
`app/Infrastructure/Members/MemberDbRepository.php:64-71`. `HasOne::create` sets the foreign key and leaves
`revoked_at`/`last_collected_at` at their null defaults, so registration produces exactly one active mandate. No DTO,
request or registration controller change is needed; the existing `NewMemberPaymentInformation` DTO still carries the
IBAN, BIC, holder and signed date.

Do not route registration through `MandateRepository::register`: a new member has no prior mandate, and the repository's
member lock is unnecessary inside the member-creation transaction. The invariant is asserted by a test instead.

## Export rows: active mandate for collections, latest mandate for refunds

`SepaBatchRepositoryDb::getInvoicesForExport` becomes a two-step read: invoice totals first, mandates second. This removes
the join that would otherwise multiply rows once a member has history.

```php
/** @return list<SepaExportInvoice> */
#[Override]
public function getInvoicesForExport(SepaBatchId $batchId): array
{
    $invoices = Invoice::query()
        ->where('invoices.sepa_batch_id', $batchId->value)
        ->where('invoices.status', InvoiceStatus::Pending)
        ->joinRelationship('lines')
        ->select(
            'invoices.id as invoice_id',
            'invoices.invoice_number',
            'invoices.member_id',
            'invoices.recipient_name',
            DB::raw('SUM(invoice_lines.price * invoice_lines.quantity) as total_price'),
            DB::raw('SUM(invoice_lines.vat * invoice_lines.quantity) as total_vat'),
        )
        ->groupBy(
            'invoices.id',
            'invoices.invoice_number',
            'invoices.member_id',
            'invoices.recipient_name',
        )
        ->get();

    $mandates = PaymentInformation::query()
        ->whereIn('member_id', $invoices->pluck('member_id')->unique()->all())
        ->orderByDesc('id')
        ->get()
        ->groupBy('member_id');

    $rows = [];

    foreach ($invoices as $invoice) {
        $memberMandates = $mandates->get($invoice->member_id) ?? collect();
        $totalPrice = (float) $invoice->total_price;

        $mandate = $totalPrice < 0
            ? $memberMandates->first()
            : $memberMandates->first(
                static fn (PaymentInformation $candidate): bool => $candidate->revoked_at === null,
            );

        if ($mandate === null) {
            continue;
        }

        $rows[] = new SepaExportInvoice(
            invoiceId: InvoiceId::create((int) $invoice->invoice_id),
            invoiceNumber: $invoice->invoice_number,
            recipientName: $invoice->recipient_name,
            total: new CompoundPrice($totalPrice, (float) $invoice->total_vat),
            iban: $mandate->banking_account_number,
            bic: $mandate->banking_bic,
            mandateId: new MandateId(
                MemberId::create((int) $invoice->member_id),
                PaymentInformationId::create($mandate->id),
            ),
            paymentInformationId: PaymentInformationId::create($mandate->id),
            mandateDate: CarbonImmutable::parse($mandate->mandate_accepted_date),
            lastCollectedAt: $mandate->last_collected_at,
        );
    }

    return $rows;
}
```

Selection rules, in words:

- **Positive invoice (collection)** → the member's active mandate. No active mandate means no row: that member is a
  manual payer and is reported as an expected exclusion by plan 02 (plan 06 builds the vocabulary on it).
- **Negative invoice (refund)** → the member's most recent mandate, *including a revoked one*. Revocation keeps the IBAN
  precisely so refunds remain possible; the credit-transfer file does not use the mandate reference at all.
- Expired mandates are included here deliberately. Blocking them is the readiness gate's job, so that finance can
  override per export; if the export query dropped them, the override could never work.
- The `status = Pending` predicate is plan 02's. Keep it exactly as written; the mandate rules here are orthogonal to
  it. The join to `members`/`payment_information` is gone, so a member with mandate history cannot duplicate a row.

`app/Domain/Billing/Payment/SepaExportInvoice.php` gains the identity and usage the later plans need:

```php
final readonly class SepaExportInvoice
{
    public function __construct(
        public InvoiceId $invoiceId,
        public string $invoiceNumber,
        public string $recipientName,
        public CompoundPrice $total,
        public string $iban,
        public string $bic,
        public MandateId $mandateId,
        public PaymentInformationId $paymentInformationId,
        public DateTimeInterface $mandateDate,
        public ?DateTimeInterface $lastCollectedAt,
    ) {}
}
```

Update the two test helpers in `tests/Feature/Infrastructure/Billing/Payment/SepaExportServiceImplTest.php` and
`tests/Unit/Domain/Billing/Payment/SepaExportInvoiceTest.php` with the new named arguments. No exporter logic changes:
`SepaExportServiceImpl` still emits `mandateId` and `mandateDate`, and plan 05 will consume `lastCollectedAt` /
`paymentInformationId` to split FRST/RCUR.

## Stamp `last_collected_at` at submission

Plan 02 owns the "mark submitted" action and the `exported_at` / `submitted_at` / `submitted_by` stamps. This plan adds
the mandate side effect inside that action, after the batch stamp.

Add to `SepaBatchRepository` (implemented in `SepaBatchRepositoryDb`):

```php
/** Mandates with a positive, non-credit invoice in the batch. @return list<PaymentInformationId> */
public function getCollectedMandateIds(SepaBatchId $batchId): array;
```

```php
/** @return list<PaymentInformationId> */
#[Override]
public function getCollectedMandateIds(SepaBatchId $batchId): array
{
    $memberIds = DB::table('invoices')
        ->join('invoice_lines', 'invoice_lines.invoice_id', '=', 'invoices.id')
        ->where('invoices.sepa_batch_id', $batchId->value)
        ->whereNull('invoices.credit_invoice_id')
        ->groupBy('invoices.id', 'invoices.member_id')
        ->havingRaw('SUM(invoice_lines.price * invoice_lines.quantity) > 0')
        ->select('invoices.member_id')
        ->get()
        ->pluck('member_id')
        ->unique();

    return PaymentInformation::query()
        ->whereIn('member_id', $memberIds->all())
        ->whereNull('revoked_at')
        ->pluck('id')
        ->map(PaymentInformationId::create(...))
        ->all();
}
```

Then the submission path:

```php
#[Override]
public function markSubmitted(SepaBatchId $batchId, int $submittedByUserId): void
{
    $submittedAt = $this->clock->now();

    $this->batchRepository->markSubmitted($batchId, $submittedByUserId, $submittedAt);

    $this->mandateRepository->stampCollections(
        new PaymentInformationIdList($this->batchRepository->getCollectedMandateIds($batchId)),
        $submittedAt,
    );
}
```

`SepaBatchServiceImpl` gains `private MandateRepository $mandateRepository` as the seventh constructor dependency
(plan 02 already extended it with `SepaExportService`, `ExportReadinessService` and `ClockInterface`). The stamping uses
the same `$this->clock->now()` value that plan 02 passes to `markSubmitted`, so the batch stamp and the mandate stamp
never differ.

`getCollectedMandateIds` deliberately does **not** reuse `getInvoicesForExport`: that query carries plan 02's
exportable-status predicate, and at submission time an already-presented collection must count even if its invoice has
since moved to `Declined` (plan 01). It selects positive, non-credit invoices by total, independent of status. A member
whose mandate was revoked between export and submission gets no stamp; the mandate is withdrawn anyway and a
re-authorisation starts a fresh mandate at FRST.

Why submission and not success:

- Presented means used: once the file is at the bank, a later return/decline (plan 01) is a failed collection *under a
  used mandate*, not an unused one. Clearing or not stamping on decline would expire a mandate that was actually used.
- Plan 01's `unlinkReversal` / decline path must not touch `last_collected_at`; no change is needed there.
- Refunds are filtered out by the positive-amount guard, so a payout never restarts a collection clock.
- Unsubmitted batches do not tick the clock. That matches the explicit "mark submitted" decision: finance confirms the
  hand-over, and only then does the mandate count as used.

## Readiness: expired mandates are surfaced, not hard-blocked

Plan 02 already built the surface: `ExportReadinessReason::ExpiredMandate` exists, maps to
`ExportReadinessCategory::Overridable`, and `isOverridable()` returns true for it. `ExportReadinessServiceImpl` already
turns `MandateReadinessStatus::Expired` into that reason, and `ExportConfirmation` / `ExportOverride` already require a
non-empty note per overridden invoice. The report, the Blade view, the Filament modal and the export gate need **no
change**.

This plan fills plan 02's mandate seam instead: `app/Infrastructure/Billing/Payment/MandateReadinessServiceImpl.php`
stops assuming one active row per member and starts returning the versioned state.

```php
final readonly class MandateReadinessServiceImpl implements MandateReadinessService
{
    public function __construct(
        private MandateExpiry $mandateExpiry,
    ) {}

    /** @param list<MemberId> $memberIds @return array<int, MandateReadiness> */
    #[Override]
    public function forMembers(array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        $rows = PaymentInformation::query()
            ->whereIn(
                'member_id',
                array_map(static fn (MemberId $id): int => $id->value, $memberIds),
            )
            ->orderByDesc('id')
            ->get()
            ->groupBy('member_id');

        $result = [];

        foreach ($rows as $memberId => $mandates) {
            $active = $mandates->first(
                static fn (PaymentInformation $mandate): bool => $mandate->revoked_at === null,
            );
            $mandate = $active ?? $mandates->first();

            if (!$mandate instanceof PaymentInformation) {
                continue;
            }

            if ($active === null) {
                $status = MandateReadinessStatus::Revoked;
            } else {
                $status = match ($mandate->state($this->mandateExpiry)) {
                    MandateState::Active => MandateReadinessStatus::Active,
                    MandateState::Expired => MandateReadinessStatus::Expired,
                    MandateState::Revoked => MandateReadinessStatus::Revoked,
                };
            }

            $result[(int) $memberId] = new MandateReadiness(
                status: $status,
                iban: $mandate->banking_account_number,
                bic: $mandate->banking_bic,
                mandateId: new MandateId(
                    MemberId::create((int) $memberId),
                    PaymentInformationId::create($mandate->id),
                ),
                signedOn: $mandate->mandate_accepted_date,
                expectedSequenceType: SequenceType::Recurring,
            );
        }

        return $result;
    }
}
```

Behaviour:

- An active row inside 36 months → `Active`, unchanged.
- An active row past 36 months → `Expired`, which plan 02 already reports as blocking-but-overridable. Finance sees it
  in the readiness report and in the export modal; a non-empty reason is stored per invoice in `sepa_export_overrides`
  for that export only. Nothing is sticky, and the mandate itself is not changed by the override.
- No active row but at least one historical row → `Revoked` (manual payer, plan 02's expected exclusion). The latest
  row's IBAN and reference are still returned, which keeps refunds exportable.
- No rows at all → omitted from the map, so plan 02 reports `Missing` (also an expected exclusion).
- `expectedSequenceType` deliberately stays `SequenceType::Recurring`. Returning `First` for a never-collected or
  re-authorised mandate here would trip plan 02's `SequenceTypeMismatch` — broken data, not overridable — on every
  export until plan 05 changes the exporter to emit FRST. The enum and the comparison already exist; plan 05 flips the
  value and the exporter together (see Hand-offs).

Because plan 02 also runs readiness as a warning at batch close, an expired mandate shows up before anyone presses the
export button, on the member view as well as in the batch.

## Invoice mail data

`InvoiceMailRepositoryDb` keeps using `member.paymentInformation`, which is now the active mandate:

- A member with an active mandate (including expired-but-not-revoked) still gets the SEPA line with the incassodatum.
- A member whose mandate was revoked has no active mandate → `sepaTransferDate` is null and the mail renders the manual
  transfer instruction. That is the revocation-to-manual-payer consequence, with no stored flag.
- The refund line's `recipientIban` currently reads `paymentInformation->iban`, a column that does not exist (gap 4's
  folded-in defect). **Leave it to plan 06**, which owns the refund-IBAN fix and must resolve the IBAN from the latest
  mandate including revoked ones (see Hand-offs). Do not silently fix it here; the ownership boundary matters because
  plan 06 also adds the refund-without-IBAN readiness listing.

```php
$mandate = $invoice->member?->paymentInformation;

return new InvoiceMailData(
    // ...
    recipientIban: $invoice->member?->paymentInformation->iban ?? '', // plan 06 fixes this
    sepaTransferDate: $mandate !== null ? $invoice->sepa_batch?->sepa_transfer_date : null,
    // ...
);
```

The only change in `getInvoiceMailData` is making the active-mandate dependency explicit (the `$mandate` local). The
`->iban` typo stays untouched.

Plan 03's declined notice leaves one literal for this plan: `mandateNeedsReauthorisation: false` in
`getInvoicePaymentDeclinedMailData()`. Fill it from the versioned mandate, using the **latest** mandate because a
revoked one is no longer returned by the active relation:

```php
$mandate = $invoice->member?->paymentInformations->first();

$mandateNeedsReauthorisation = $mandate !== null
    && $mandate->state($this->mandateExpiry) !== MandateState::Active;
```

```php
// eager load the history for the declined mail query
->with(['member.paymentInformations', 'lines', 'sepaBatch'])
```

Revoked or expired → `true` (the notice tells the member to sign a new mandate); an active mandate or no mandate at all
→ `false`, which is plan 03's contract. `InvoiceMailRepositoryDb` gains `private MandateExpiry $mandateExpiry` as a
constructor dependency; its feature test should resolve the repository from the container (or construct it with a fixed
clock) instead of `new InvoiceMailRepositoryDb()`.

## Matching incoming credits

`InvoiceRepositoryDb::findMatchingCredit` matches a manual transfer to an invoice by IBAN. After revocation, a member may
still transfer from the account of the old mandate — that money is real and must settle the invoice. Match across the
whole mandate history instead of only the active row:

```php
->whereHas('member.paymentInformations', static function ($query) use ($bankingAccountNumber): void {
    $query->where('banking_account_number', $bankingAccountNumber);
})
```

`paymentInformations` is the unfiltered history relation, so an old IBAN keeps matching. The amount (±0.01) and date
(±30 days) guards are unchanged. The active relation is still the right source for *suggestions*
(`AttachBankTransactionAction`), because that is where the next collection will go; a revoked row is only a fallback for
purchase-order payouts and refunds.

`MemberCreditorPrefill` must not crash for a member without an active mandate. Use the active mandate when present and
fall back to the latest historical one so a payout can still be prefilled:

```php
$member->loadMissing(['paymentInformation', 'paymentInformations']);

$mandate = $member->paymentInformation ?? $member->paymentInformations->first();

return [
    'creditor_name' => MemberNameFormatter::presentationName(...),
    'creditor_iban' => $mandate?->banking_account_number,
];
```

## Admin surface

### Mandate relation manager

Add `app/Filament/Admin/Resources/Members/RelationManagers/MandatesRelationManager.php` and register it in
`MemberResource::getRelations()` before `InvoicesRelationManager`.

```php
final class MandatesRelationManager extends MemberRelationManager
{
    #[Override]
    protected static string $relationship = 'paymentInformations';

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if (!$user instanceof User || $user->isMember()) {
            return false;
        }

        return $user->can('view_member_payment_information');
    }

    #[Override]
    public function isReadOnly(): bool
    {
        return !(auth()->user()?->can('update_member_payment_information') ?? false);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mandate_reference')
                    ->label(__('labels.mandate_reference')),
                TextColumn::make('banking_account_number')
                    ->label(__('labels.banking_account_number')),
                TextColumn::make('mandate_accepted_date')
                    ->label(__('labels.mandate_date'))
                    ->date(),
                TextColumn::make('last_collected_at')
                    ->label(__('labels.last_collected_at'))
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('mandate_state')
                    ->label(__('labels.mandate_state'))
                    ->badge()
                    ->state(static fn (PaymentInformation $record): MandateState => $record->state(app(MandateExpiry::class)))
                    ->formatStateUsing(static fn (MandateState $state): string => __('labels.mandate_states.' . $state->value))
                    ->color(static fn (MandateState $state): string => match ($state) {
                        MandateState::Active => 'success',
                        MandateState::Expired => 'warning',
                        MandateState::Revoked => 'gray',
                    }),
                TextColumn::make('revoked_at')
                    ->label(__('labels.revoked_at'))
                    ->date()
                    ->placeholder('—'),
            ])
            ->defaultSort('mandate_accepted_date', 'desc')
            ->headerActions([
                Action::make('createMandate')
                    ->label(__('labels.new_mandate'))
                    ->icon(Heroicon::Plus)
                    ->visible(static fn (): bool => auth()->user()?->can('update_member_payment_information') ?? false)
                    ->modalDescription(__('labels.new_mandate_description'))
                    ->schema(self::mandateFields())
                    ->action(static function (
                        RelationManager $livewire,
                        array $data,
                        MandateRepository $mandates,
                    ): void {
                        /** @var Member $member */
                        $member = $livewire->getOwnerRecord();

                        $mandates->register(
                            MemberId::create($member->id),
                            new MandateDetails(
                                iban: (string) $data['banking_account_number'],
                                bic: (string) $data['banking_bic'],
                                accountHolderName: (string) $data['banking_account_holder_name'],
                                signedAt: CarbonImmutable::parse((string) $data['mandate_accepted_date']),
                            ),
                        );
                    })
                    ->successNotificationTitle(__('notifications.mandate_created'))
                    ->after(static fn (Component $livewire) => $livewire->dispatch('refresh')),
            ])
            ->recordActions([
                Action::make('editMandate')
                    ->label(__('labels.edit'))
                    ->icon(Heroicon::PencilSquare)
                    ->modalDescription(__('labels.edit_mandate_help'))
                    ->visible(static fn (PaymentInformation $record): bool =>
                        $record->revoked_at === null
                        && (auth()->user()?->can('update_member_payment_information') ?? false))
                    ->fillForm(static fn (PaymentInformation $record): array => [
                        'banking_account_number' => $record->banking_account_number,
                        'banking_bic' => $record->banking_bic,
                        'banking_account_holder_name' => $record->banking_account_holder_name,
                        'mandate_accepted_date' => $record->mandate_accepted_date,
                    ])
                    ->schema(self::editMandateFields())
                    ->action(static function (
                        PaymentInformation $record,
                        array $data,
                        MandateRepository $mandates,
                    ): void {
                        $mandates->correctDetails(
                            PaymentInformationId::create($record->id),
                            new MandateCorrection(
                                bic: (string) $data['banking_bic'],
                                accountHolderName: (string) $data['banking_account_holder_name'],
                            ),
                        );
                    })
                    ->successNotificationTitle(__('notifications.mandate_updated')),
                Action::make('revokeMandate')
                    ->label(__('labels.revoke_mandate'))
                    ->icon(Heroicon::XCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('labels.revoke_mandate_warning'))
                    ->visible(static fn (PaymentInformation $record): bool =>
                        $record->revoked_at === null
                        && (auth()->user()?->can('update_member_payment_information') ?? false))
                    ->action(static function (PaymentInformation $record, MandateRepository $mandates): void {
                        $mandates->revoke(
                            PaymentInformationId::create($record->id),
                            CarbonImmutable::now(),
                        );
                    })
                    ->successNotificationTitle(__('notifications.mandate_revoked')),
            ]);
    }

    /** @return array<int, TextInput|DatePicker> */
    private static function mandateFields(): array
    {
        return [
            TextInput::make('banking_account_number')
                ->label(__('labels.banking_account_number'))
                ->required()
                ->maxLength(34)
                ->rule(new Iban()),
            TextInput::make('banking_bic')
                ->label(__('labels.banking_bic'))
                ->required()
                ->maxLength(11)
                ->rule(new Bic()),
            TextInput::make('banking_account_holder_name')
                ->label(__('labels.banking_account_holder_name'))
                ->required()
                ->maxLength(255),
            DatePicker::make('mandate_accepted_date')
                ->label(__('labels.mandate_date'))
                ->required()
                ->native(false)
                ->maxDate(now()),
        ];
    }

    /**
     * IBAN and signed date identify the signed authorisation, so the edit form shows them read-only.
     * A change to either is a re-authorisation and goes through the "Nieuwe machtiging" action.
     *
     * @return array<int, TextInput|DatePicker>
     */
    private static function editMandateFields(): array
    {
        return [
            TextInput::make('banking_account_number')
                ->label(__('labels.banking_account_number'))
                ->disabled()
                ->dehydrated(false),
            TextInput::make('banking_bic')
                ->label(__('labels.banking_bic'))
                ->required()
                ->maxLength(11)
                ->rule(new Bic()),
            TextInput::make('banking_account_holder_name')
                ->label(__('labels.banking_account_holder_name'))
                ->required()
                ->maxLength(255),
            DatePicker::make('mandate_accepted_date')
                ->label(__('labels.mandate_date'))
                ->format('d-m-Y')
                ->native(false)
                ->disabled()
                ->dehydrated(false),
        ];
    }
}
```

Notes:

- Editing is limited to the BIC and the account holder name. The IBAN and the signed date are displayed read-only:
  they identify the signed authorisation and the account it covers. Changing either is a re-authorisation, so finance
  uses **Nieuwe machtiging**; `register()` revokes the old row, issues a new reference and starts the new mandate at
  FRST. This is the only path that may change an IBAN or a signed date, and it never rewrites history in place.
- There is no separate "re-authorise" action. Creating a mandate *is* re-authorisation when an active mandate exists; the
  modal description says so and `register` revokes the previous row in the same transaction.
- The relation manager deliberately overrides `isReadOnly()` instead of inheriting the pending-member freeze: mandate
  data is financial data that may need correction before the member is accepted. The permission gate is the real guard.
- `mandate_reference` is the model accessor; it is not sortable or searchable because it is derived. That is acceptable
  for a per-member table.
- The create action is the fix for the not-null `mandate_accepted_date` insert: the signed date is a required, hydrated
  field here instead of a disabled, non-dehydrated one.

### Member payment tab

Replace the relationship grid in `MemberForm` with a read-only summary. Remove `Grid::make()->relationship('paymentInformation')`
and the `uuid` field, and drop the now-unused `Grid` import (`KeyValue` is still used by the registration tab). Keep the
tab visibility on `view-payment-information`.

```php
Tabs\Tab::make(__('labels.payment_information'))
    ->schema([
        TextInput::make('mandate_reference')
            ->label(__('labels.mandate_reference'))
            ->formatStateUsing(static fn (?Member $record): string =>
                $record?->paymentInformation?->mandate_reference ?? __('labels.no_active_mandate'))
            ->disabled()
            ->dehydrated(false),
        TextInput::make('mandate_state')
            ->label(__('labels.mandate_state'))
            ->formatStateUsing(static fn (?Member $record): string =>
                $record?->paymentInformation === null
                    ? __('labels.no_active_mandate')
                    : __('labels.mandate_states.' . $record->paymentInformation->state(app(MandateExpiry::class))->value))
            ->disabled()
            ->dehydrated(false),
        TextInput::make('active_mandate_iban')
            ->label(__('labels.banking_account_number'))
            ->formatStateUsing(static fn (?Member $record): string => $record?->paymentInformation?->banking_account_number ?? '—')
            ->disabled()
            ->dehydrated(false),
        TextInput::make('active_mandate_bic')
            ->label(__('labels.banking_bic'))
            ->formatStateUsing(static fn (?Member $record): string => $record?->paymentInformation?->banking_bic ?? '—')
            ->disabled()
            ->dehydrated(false),
        TextInput::make('active_mandate_holder')
            ->label(__('labels.banking_account_holder_name'))
            ->columnSpanFull()
            ->formatStateUsing(static fn (?Member $record): string => $record?->paymentInformation?->banking_account_holder_name ?? '—')
            ->disabled()
            ->dehydrated(false),
        TextInput::make('active_mandate_signed_at')
            ->label(__('labels.mandate_date'))
            ->formatStateUsing(static fn (?Member $record): ?string => $record?->paymentInformation?->mandate_accepted_date?->format('d-m-Y'))
            ->disabled()
            ->dehydrated(false),
        TextInput::make('active_mandate_last_collected_at')
            ->label(__('labels.last_collected_at'))
            ->formatStateUsing(static fn (?Member $record): string => $record?->paymentInformation?->last_collected_at?->format('d-m-Y') ?? '—')
            ->disabled()
            ->dehydrated(false),
        TextInput::make('active_mandate_expires_at')
            ->label(__('labels.mandate_expires_at'))
            ->formatStateUsing(static fn (?Member $record): string =>
                $record?->paymentInformation === null
                    ? '—'
                    : app(MandateExpiry::class)
                        ->expiresAt($record->paymentInformation->mandate_accepted_date, $record->paymentInformation->last_collected_at)
                        ->format('d-m-Y'))
            ->disabled()
            ->dehydrated(false),
        TextInput::make('mandate_expired_notice')
            ->hiddenLabel()
            ->columnSpanFull()
            ->formatStateUsing(static fn (?Member $record): ?string =>
                $record?->paymentInformation !== null
                && $record->paymentInformation->state(app(MandateExpiry::class)) === MandateState::Expired
                    ? __('labels.mandate_expired_notice')
                    : null)
            ->disabled()
            ->dehydrated(false),
    ])
    ->visible(static fn (Member $record): bool => auth()->user()?->can('view-payment-information', $record) ?? false),
```

This makes the active mandate's reference, state and expiry visible on the member view (which renders the form
read-only) and no longer offers a broken write path on save. Write actions live in the relation manager.

### Member list column

Add a derived column to `MembersTable` and eager-load the relation in `MemberResource::getEloquentQuery()` to avoid an
N+1:

```php
// MemberResource
$query = parent::getEloquentQuery()->with('paymentInformation');

// MembersTable
TextColumn::make('mandate_state')
    ->label(__('labels.mandate_state'))
    ->badge()
    ->state(static fn (Member $record): ?MandateState =>
        $record->paymentInformation?->state(app(MandateExpiry::class)))
    ->formatStateUsing(static fn (?MandateState $state): string =>
        $state === null ? __('labels.no_active_mandate') : __('labels.mandate_states.' . $state->value))
    ->color(static fn (?MandateState $state): string => match ($state) {
        MandateState::Active => 'success',
        MandateState::Expired => 'warning',
        MandateState::Revoked => 'gray',
        null => 'gray',
    }),
```

The column reports the *mandate* state, not a payment method. Plan 06 owns the explicit "handmatige betaler" label,
filter and reporting on top of `state === null` and `revoked`; no payment-method column is added here or there.

## Permissions

No new permission is introduced. The mandate surface reuses the existing pair:

- Viewing the relation manager and the summary requires `view_member_payment_information`.
- Creating, editing and revoking require `update_member_payment_information`.

`database/seeders/RolePermissionSeeder.php` already grants both to `financial_administration` only (line 95), and
`MemberAdministration` does not have them. The `canViewForRecord` override additionally excludes member users, whose
`MemberPolicy::viewPaymentInformation` deliberately allows self-service viewing of their own record. This delivers
"finance/admin roles only" without adding a second authorisation concept.

`MemberPolicy` needs no change: `updatePaymentInformation` already returns the permission, which is what the actions
check. `InvoicePolicy` is untouched — mandates do not gate invoice transitions; the readiness gate stays at export.
This project does not use Filament Shield's generated policies: `ResourcePermission` and `RolePermissionSeeder` are
manual, so there is no policy file to regenerate after adding the UI. No new `RoleName` and no new role either.

## Labels

Add to `lang/nl/labels.php` (next to the existing payment block around line 50):

```php
'mandate' => 'Machtiging',
'mandates' => 'Machtigingen',
'mandate_reference' => 'Machtigingsreferentie',
'mandate_state' => 'Status machtiging',
'mandate_states' => [
    'active' => 'Actief',
    'expired' => 'Verlopen',
    'revoked' => 'Ingetrokken',
],
'last_collected_at' => 'Laatst geïncasseerd',
'revoked_at' => 'Ingetrokken op',
'mandate_expires_at' => 'Verloopt op',
'no_active_mandate' => 'Geen actieve machtiging',
'new_mandate' => 'Nieuwe machtiging',
'new_mandate_description' => 'Een nieuwe machtiging trekt de huidige actieve machtiging in. De nieuwe machtiging krijgt een eigen referentie; de eerste incasso is opnieuw een eerste incasso.',
'edit_mandate' => 'Machtiging wijzigen',
'edit_mandate_help' => 'Alleen de BIC en de naam van de rekeninghouder zijn aan te passen. Voor een ander IBAN of een nieuwe ondertekeningsdatum maakt u een nieuwe machtiging aan; die krijgt een eigen referentie en begint opnieuw met een eerste incasso.',
'revoke_mandate' => 'Machtiging intrekken',
'revoke_mandate_warning' => 'De machtiging wordt ingetrokken. Het IBAN blijft bewaard voor eventuele terugbetalingen; het lid wordt handmatige betaler.',
'mandate_expired_notice' => 'Deze machtiging is 36 maanden niet gebruikt en daarmee verlopen. Vraag een nieuwe machtiging aan, of bevestig bij de export met een reden dat er toch geïncasseerd mag worden.',
```

The readiness report's expired-mandate label already exists from plan 02 as
`labels.export_readiness.expired_mandate`; do not add a duplicate.

Add to `lang/nl/notifications.php`:

```php
'mandate_created' => 'Machtiging vastgelegd',
'mandate_updated' => 'Machtiging bijgewerkt',
'mandate_revoked' => 'Machtiging ingetrokken',
```

`labels.reference` stays; it is still used by the bookkeeping infolist.

## Tests

All tests are PHPUnit classes and use the existing factories. Create new files with
`./Taskfile artisan make:test --phpunit <name>` and the migrations with `./Taskfile artisan make:migration`. Run each
file after changing it. Add factory states rather than hand-building rows:

```php
// database/factories/PaymentInformationFactory.php
public function definition(): array
{
    return [
        'member_id' => Member::factory(),
        'banking_account_number' => fake()->iban('NL'),
        'banking_bic' => fake()->swiftBicNumber(),
        'banking_account_holder_name' => fake()->name(),
        // Within the 36-month window: a random date since 1970 would make
        // factory-made mandates expired and break unrelated export tests.
        'mandate_accepted_date' => fake()->dateTimeBetween('-1 year', 'now'),
        'revoked_at' => null,
        'last_collected_at' => null,
    ];
}

public function signedAt(DateTimeInterface $signedAt): self
{
    return $this->state(static fn (array $_attributes): array => [
        'mandate_accepted_date' => $signedAt,
    ]);
}

public function collectedAt(DateTimeInterface $collectedAt): self
{
    return $this->state(static fn (array $_attributes): array => [
        'last_collected_at' => $collectedAt,
    ]);
}

public function revoked(?DateTimeInterface $revokedAt = null): self
{
    return $this->state(static fn (array $_attributes): array => [
        'revoked_at' => $revokedAt ?? now(),
    ]);
}
```

### Unit — expiry rule

`tests/Unit/Domain/Billing/Payment/MandateExpiryTest.php` (use `ClockExpectation`):

- `test_state_returns_active_thirty_five_months_after_the_last_collection`
- `test_state_returns_expired_at_exactly_thirty_six_months`
- `test_state_returns_expired_thirty_seven_months_after_the_last_collection`
- `test_state_counts_from_the_signing_date_when_the_mandate_was_never_collected`
- `test_state_counts_from_the_last_collection_and_not_the_signing_date`
- `test_state_returns_revoked_even_when_the_mandate_is_also_expired`
- `test_expires_at_clamps_month_ends_without_overflow` (`2024-01-31` → `2027-01-31`; `2024-02-29` → `2027-02-28`)

### Unit — domain DTOs

- `tests/Unit/Domain/Billing/Payment/MandateDetailsTest.php`: `test_it_stores_all_properties`.
- `tests/Unit/Domain/Billing/Payment/MandateCorrectionTest.php`: `test_it_stores_the_bic_and_account_holder_name`.
- `tests/Unit/Domain/Billing/Payment/PaymentInformationIdListTest.php`:
  `test_it_rejects_a_non_payment_information_id`, `test_from_array_maps_values`.

### Unit — submission stamping

`tests/Unit/Domain/Billing/Payment/SepaBatchServiceImplTest.php` and `SepaBatchServiceTest.php` (both construct the same
service and need the new `MandateRepository` argument):

- `test_mark_submitted_stamps_the_collected_mandates` — bind `ClockExpectation` to a fixed time; expect `markSubmitted`
  on the batch repository with that time, return two mandate ids from `getCollectedMandateIds`, and expect
  `stampCollections` with exactly those ids and the same time.
- `test_mark_submitted_stamps_an_empty_list_when_the_batch_collected_nothing` — `getCollectedMandateIds` returns an empty
  list; `stampCollections` is still called with an empty list (the repository short-circuits).

Add `expectsGetCollectedMandateIds` and `expectsStampCollections` to the existing expectation classes
(`SepaBatchRepositoryExpectation`, plus a new `MandateRepositoryExpectation`).

### Feature — mandate readiness seam

Extend `tests/Feature/Infrastructure/Billing/Payment/MandateReadinessServiceImplTest.php` (created by plan 02).
Construct the implementation with `new MandateReadinessServiceImpl(new MandateExpiry($clockExpectation->mock))` so the
36-month boundary is deterministic, or resolve it from the container and `travelTo`.

- `test_for_members_returns_active_for_a_mandate_within_thirty_six_months`
- `test_for_members_returns_expired_after_thirty_six_months`
- `test_for_members_returns_revoked_for_a_member_with_only_a_revoked_mandate`
- `test_for_members_returns_the_latest_revoked_mandate_for_the_refund_iban`
- `test_for_members_does_not_duplicate_a_member_with_mandate_history`
- `test_for_members_returns_missing_for_a_member_without_any_mandate` (the member is omitted from the map)
- `test_for_members_keeps_expected_sequence_type_recurring_until_plan_05`

Plan 02's `test_for_members_ignores_a_soft_deleted_mandate` is replaced by the revoked-mandate case: soft deletes no
longer exist on the table, and the backfill turned those rows into revoked mandates. Plan 02's Filament test that faked
`Expired` gains a real-data counterpart in the scenario file below.

### Feature — model and reference

`tests/Feature/Models/PaymentInformationTest.php`:

- `test_existing_mandate_keeps_its_derived_reference_after_the_lifecycle_migration` — create a member with id `42` and
  a mandate with id `7`, assert `mandate_reference === 'C000042-000007'`. This is the regression guard for "the migration
  never changes identity": the reference is computed from the preserved row id, not from a new column or a new table.
- `test_a_member_can_have_a_revoked_history_row_and_one_active_row` — proves the old unique constraint is gone and the
  partial index is what keeps the active row single.
- `test_state_returns_active_for_a_non_revoked_mandate`
- `test_state_returns_expired_after_thirty_six_months`
- `test_state_returns_revoked`

### Feature — repository and invariants

`tests/Feature/Infrastructure/Billing/Payment/MandateRepositoryDbTest.php`:

- `test_register_creates_the_first_active_mandate` — member without payment information; assert one row, `revoked_at === null`,
  signed date and IBAN stored.
- `test_register_creates_a_new_active_mandate_and_revokes_the_old_one` — pre-existing active mandate; assert old `revoked_at` set,
  new row active, exactly one active, and the new reference differs from the old one (different row id).
- `test_register_keeps_the_iban_on_the_revoked_mandate` — revocation must not blank the account.
- `test_register_creates_a_new_reference_so_the_first_collection_is_first_again` — assert `new MandateId(...)` differs and the old row
  still reports its original reference.
- `test_correct_details_changes_the_bic_and_account_holder_name`
- `test_correct_details_leaves_the_iban_and_signed_date_untouched`
- `test_correct_details_leaves_a_revoked_mandate_untouched`
- `test_revoke_sets_revoked_at_and_keeps_the_iban`
- `test_revoke_is_idempotent`
- `test_stamp_collections_sets_last_collected_at`
- `test_stamp_collections_does_not_move_last_collected_at_backwards`
- `test_stamp_collections_ignores_an_empty_list`
- `test_one_active_mandate_per_member_is_enforced_by_the_database` — expect a `QueryException` when inserting a second row with
  `revoked_at = null` directly.

`tests/Feature/Infrastructure/Billing/Payment/MandateLifecycleBackfillTest.php` (creates a legacy-shaped temporary table
`payment_information_backfill_test` via `Schema` in `setUp`, drops it in `tearDown`, and runs
`MandateLifecycleBackfill('payment_information_backfill_test')`):

- `test_backfill_marks_soft_deleted_rows_as_revoked`
- `test_backfill_keeps_the_iban_of_a_soft_deleted_row`
- `test_backfill_stamps_last_collected_at_from_completed_batches`
- `test_backfill_stamps_last_collected_at_from_exported_batches`
- `test_backfill_ignores_open_and_pending_batches`
- `test_backfill_ignores_credit_invoices`
- `test_backfill_ignores_a_refund_only_batch` (negative invoice; the clock must not start from a payout)
- `test_backfill_does_not_overwrite_an_existing_last_collected_at`

### Feature — export rows

Extend `tests/Feature/Infrastructure/Billing/Payment/SepaBatchRepositoryDbTest.php` and adjust
`test_get_invoices_for_export` for the new DTO fields:

- `test_get_invoices_for_export_uses_only_the_active_mandate_for_a_collection`
- `test_get_invoices_for_export_does_not_duplicate_a_member_with_mandate_history` (one active + one revoked → one row)
- `test_get_invoices_for_export_excludes_a_collection_without_an_active_mandate`
- `test_get_invoices_for_export_uses_the_latest_mandate_for_a_refund_after_revocation` (negative invoice still exportable, IBAN kept)
- `test_get_invoices_for_export_exposes_the_mandate_id_and_last_collected_at`
- keep/extend `test_get_invoices_for_export` to assert `paymentInformationId` and the reference `C000001-000001`.
- `test_get_collected_mandate_ids_returns_the_active_mandate_of_a_positive_invoice`
- `test_get_collected_mandate_ids_ignores_a_credit_invoice`
- `test_get_collected_mandate_ids_ignores_a_member_without_an_active_mandate`
- `test_get_collected_mandate_ids_counts_a_presented_invoice_even_after_it_is_declined` (status change does not remove
  the stamping evidence)
- `test_get_collected_mandate_ids_returns_each_mandate_once`

Extend `tests/Unit/Domain/Billing/Payment/SepaExportInvoiceTest.php` with the two new properties.

### Feature — matching

Extend `tests/Feature/Infrastructure/Billing/Invoices/InvoiceRepositoryDbMatchingTest.php`:

- `test_find_matching_credit_matches_the_iban_of_a_revoked_mandate`
- `test_find_matching_credit_still_prefers_the_amount_within_tolerance` (unchanged guard, proves the widened IBAN match did not
  loosen the amount check)

### Feature — invoice mail

Extend `tests/Feature/Infrastructure/Billing/Invoices/InvoiceMailRepositoryDbTest.php`:

- `test_get_invoice_mail_data_uses_the_active_mandate_for_the_sepa_transfer_date` (existing test still valid)
- `test_get_invoice_mail_data_has_no_sepa_transfer_date_when_the_mandate_is_revoked`
- `test_invoice_mail_renders_for_a_member_with_an_active_mandate` — fetch the data through the repository, build the
  `InvoiceMail` mailable with a `SepaConfiguration`, and assert `render()` contains the incassodatum text.
- `test_declined_mail_flags_a_revoked_mandate_for_reauthorisation` (plan 03's `mandateNeedsReauthorisation` contract)
- `test_declined_mail_flags_an_expired_mandate_for_reauthorisation`
- `test_declined_mail_does_not_flag_an_active_mandate`
- `test_declined_mail_does_not_flag_a_member_without_a_mandate`

### Feature — Filament

`tests/Feature/Filament/Admin/Resources/Members/MandateLifecycleTest.php` (`WithAuthorizedUser`, `Livewire::test` on
`MandatesRelationManager` with `ownerRecord`/`pageClass = ViewMember::class`):

- `test_financial_administration_can_create_a_mandate_for_a_member_without_payment_information`
- `test_creating_a_mandate_revokes_the_previous_active_mandate_and_uses_a_new_reference`
- `test_create_mandate_requires_an_iban_bic_holder_and_signed_date`
- `test_create_mandate_rejects_an_invalid_iban`
- `test_financial_administration_can_edit_the_active_mandate` (BIC and holder name)
- `test_edit_form_shows_the_iban_and_signed_date_read_only`
- `test_edit_action_cannot_change_the_iban_or_signed_date` (submit forged form data; assert the row is unchanged)
- `test_edit_action_is_hidden_for_a_revoked_mandate`
- `test_financial_administration_can_revoke_the_active_mandate`
- `test_revoke_keeps_the_iban_and_the_member_becomes_a_manual_payer` (no active mandate remains; the invoice mail data then has a
  null `sepaTransferDate`)
- `test_revoke_action_is_hidden_for_a_revoked_mandate`
- `test_member_administration_cannot_view_the_mandate_relation_manager` (`canViewForRecord` is false)
- `test_member_user_cannot_view_the_mandate_relation_manager` (own record, still false)
- `test_members_list_shows_the_mandate_state` (`assertTableColumnStateSet('mandate_state', MandateState::Expired, $member)`)

Extend the member form test surface (same file or a small `MemberPaymentTabTest`):

- `test_payment_tab_shows_the_active_mandate_reference_and_state` (`Livewire::test(EditMember::class, ...)->assertSee('C000042-000007')`)
- `test_payment_tab_no_longer_renders_the_uuid_reference` (`assertFormFieldDoesNotExist('uuid')`)
- `test_payment_tab_shows_no_active_mandate_after_revocation`

Do **not** add `MandatesRelationManager` to the generic lists in
`tests/Feature/Authorization/MemberRelationManagersVisibilityTest.php` and
`tests/Feature/Authorization/MemberPendingRelationManagersTest.php`; both assert the opposite of this manager's
member-user exclusion and pending-member writability. The dedicated assertions above cover it.

### Feature — registration

- Extend `tests/Feature/Infrastructure/Members/MemberDbRepositoryTest.php`:
  `test_new_member_creates_member_and_payment_information` gains assertions that exactly one mandate exists,
  `revoked_at` is null and `last_collected_at` is null.
- Extend `tests/Feature/Http/Controllers/Registration/RegistrationControllerTest.php`:
  `test_payment_information_post_stores_data_and_redirects` asserts the stored row is active (`revoked_at` null). The
  full registration path must stay green.

### Feature — scenario

`tests/Feature/Scenarios/MandateLifecycleScenarioTest.php`:

- `test_submitting_a_batch_stamps_the_mandates_last_collected_at` — batch with one collection;
  `SepaBatchService::markSubmitted($batchId, $userId)`; assert the mandate's `last_collected_at` equals the batch's
  `submitted_at`.
- `test_a_refund_does_not_stamp_last_collected_at` — negative invoice in the batch, submit, assert the mandate's timestamp
  stays null.
- `test_a_collection_declined_after_submission_still_counts_as_use` — submit, then decline the invoice through plan 01's
  service, assert `last_collected_at` survives; advance 35 months → `MandateState::Active`.
- `test_an_expired_mandate_is_reported_as_an_overridable_exclusion` — travel 36 months,
  `ExportReadinessService::assess($batchId)`, assert an `ExportReadinessIssue` with
  `ExportReadinessReason::ExpiredMandate` for the invoice, `category() === Overridable` and `isBlocked()` true.
- `test_an_expired_mandate_can_be_collected_with_a_reason` — call
  `SepaBatchService::export($batchId, new ExportConfirmation(true, [new ExportOverride($invoiceId, ExportReadinessReason::ExpiredMandate, 'Afgestemd met penningmeester')]), $userId)`;
  assert the export succeeds and `sepa_export_overrides` holds the reason.
- `test_the_expired_mandate_override_does_not_apply_to_the_next_export` — a second export with an empty
  `ExportConfirmation` throws `ExportNotReadyException` again.

These are integration tests against plan 02's real gate, not a faked mandate service; they prove the seam wiring, not
just the mapping.

## Plan 02 surface this plan extends

[Plan 02](20261022_02-export-readiness-submission-tracking.md) lands before this plan and already defines the identifiers
this plan plugs into:

- `ExportReadinessReason::ExpiredMandate` (category `ExportReadinessCategory::Overridable`, `isOverridable()` true),
  `ExportReadinessIssue`, `ExportReadinessReport` and `ExportReadinessServiceImpl` in
  `app/Domain/Billing/Payment/Readiness/`.
- `MandateReadinessStatus` (`Active`, `Missing`, `Revoked`, `Expired`), `MandateReadiness`, the
  `MandateReadinessService` interface and `MandateReadinessServiceImpl` — the one implementation this plan rewrites.
- `ExportConfirmation` / `ExportOverride` and the append-only `sepa_export_overrides` table, which make the expired
  mandate's reason mandatory per export.
- `SepaBatchService::markSubmitted(SepaBatchId $batchId, int $submittedByUserId)` and
  `SepaBatchRepository::markSubmitted(...)`, where the mandate stamping hook goes.
- `getInvoicesForExport` with its `status = Pending` predicate (`SepaBatchRepositoryDb`), which this plan's rewrite
  keeps.

Do not add a new exclusion kind, override table or readiness service. The plan 02 surface is enough; this plan only
fills the mandate seam and rewrites the export rows.

## Hand-offs

- **Plan 05 (FRST/RCUR)** consumes the mandate model and `last_collected_at`. `null` means FRST; a stamped mandate is
  RCUR. The backfill in this plan keeps existing mandates RCUR instead of restarting the whole membership at FRST.
  `SepaExportInvoice` now exposes `paymentInformationId` and `lastCollectedAt` for the split, and
  `MandateReadiness::$expectedSequenceType` stays `Recurring` until plan 05 can make the exporter emit FRST. Plan 05
  flips `expectedSequenceType`, `SepaCollectionCandidate::$sequenceType` and the exporter together, otherwise plan 02's
  `SequenceTypeMismatch` blocks every first collection. One trap to resolve there: `last_collected_at` is stamped at
  *submission*, so a re-export of an already-submitted batch would flip from FRST to RCUR; plan 05 must keep the
  sequence stable per collection, not re-derive it on every export.
- **Plan 02 (readiness & submission)** owns the surface, the override storage and the submission stamps. This plan adds
  the expired-mandate status behind the existing reason, the `last_collected_at` stamping hook and the active-mandate
  export rows. It also
  relies on plan 02 keeping its status predicate when `getInvoicesForExport` is restructured here.
- **Plan 03 (declined-SEPA notice)** left exactly one hook: the `mandateNeedsReauthorisation` literal in
  `InvoiceMailRepositoryDb::getInvoicePaymentDeclinedMailData()`. This plan fills it from the latest mandate (revoked or
  expired → true) as described in the invoice mail section. A decline does not revoke a mandate; a presented-then-declined
  collection counts as use.
- **Plan 06 (manual payers)** derives the state from the absence of an active mandate. Revocation is now the only way to
  become a manual payer; do not add a stored payment-method column. Because `paymentInformation` is now active-only,
  plan 06 must resolve the refund IBAN from `paymentInformations()->first()` (history, including revoked). The
  `recipientIban` typo in `InvoiceMailRepositoryDb` remains plan 06's fix, and its refund-without-IBAN readiness listing
  must include members who never had a mandate at all.
- **Plan 01 (declined-payment recovery)** must not clear `last_collected_at` on decline, reopen or `unlinkReversal`.
  The scenario test above locks that in.

## Out of scope

- Storing the signed mandate document or handling its retention period. The application keeps the data, not the scan.
- `FNAL` (final collection) sequence handling; plan 05 covers FRST/RCUR only.
- A production backfill runbook, dry-run tooling or data verification reports. The backfill migration runs in place; a
  production rollout is an operational task.
- Audit trail: no `created_by`, `revoked_by` or revocation-reason columns. Who did what stays out, as decided for the
  map.
- Editing or deleting historical mandates. Revoked and expired rows are read-only and never removed.
- A scheduled expiry job. Expiry is derived on read, by decision.
- The refund-IBAN mail fix, the refund-without-IBAN readiness rows and the explicit manual-payer vocabulary/filter
  (plan 06).
- The FRST/RCUR split (plan 05) and the declined-SEPA notice (plan 03).

## Verification

```bash
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/MandateExpiryTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/MandateDetailsTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/MandateCorrectionTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/PaymentInformationIdListTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/SepaExportInvoiceTest.php
./Taskfile artisan test --compact tests/Feature/Models/PaymentInformationTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Payment/MandateRepositoryDbTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Payment/MandateLifecycleBackfillTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Payment/MandateReadinessServiceImplTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Payment/SepaBatchRepositoryDbTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/SepaBatchServiceImplTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/SepaBatchServiceTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Invoices/InvoiceRepositoryDbMatchingTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Invoices/InvoiceMailRepositoryDbTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Members/MemberDbRepositoryTest.php
./Taskfile artisan test --compact tests/Feature/Http/Controllers/Registration/RegistrationControllerTest.php
./Taskfile artisan test --compact tests/Feature/Filament/Admin/Resources/Members/MandateLifecycleTest.php
./Taskfile artisan test --compact tests/Feature/Scenarios/MandateLifecycleScenarioTest.php
./Taskfile stan
./Taskfile lint
```

`SepaBatchServiceTest.php` and `SepaBatchServiceImplTest.php` are both unit-test files for the same service; when the
constructor gains `MandateRepository`, update both. This plan's migrations are numbered `110000`–`110002`, after plan
02's `100000`–`100002`, so `sepa_batches.exported_at` exists when the backfill reads it and the lifecycle DDL runs
after plan 02's export columns.
