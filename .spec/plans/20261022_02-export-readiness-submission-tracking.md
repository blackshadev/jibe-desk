# Export readiness & submission tracking — implementation plan

Date: 2026-10-22
Backlog step: 2 of 13 in [Prioritize the confirmed backlog and decide the plan split](../maps/20261021-missing-features/tickets/07-prioritize-confirmed-backlog-and-decide-plan-split.md)
Decisions source: [Judge SEPA & payment robustness candidates](../maps/20261021-missing-features/tickets/04-judge-sepa-payment-robustness-candidates.md), gap 5

## Overview

Turn the SEPA export from "zip whatever the exporter produces" into a checked action. A readiness assessment runs
as a warning when a batch is closed and as a hard gate when it is exported. It reports every invoice that would not
be collected, split into expected manual-payer exclusions and broken data that must be fixed, and it records an
explicit, per-export override when finance decides to collect on an expired mandate anyway.

This plan also adds the missing collection status predicate, the notice-period guard between the invoice mail and the
incassodatum, the submission stamps with an explicit "mark submitted" action, and the hygiene fix that makes the
scheduled monthly batch sweep purchase orders into the batch like the manual create page already does.

Two seams are defined now and filled later: the mandate readiness seam (plan 04 owns the versioned mandate model) and
the FRST/RCUR sequence-type comparison (plan 05 owns the source of truth). Neither requires rewriting the gate when it
lands.

## Current situation

- `app/Infrastructure/Billing/Payment/SepaBatchRepositoryDb.php:107-150` — `getInvoicesForExport` selects every
  invoice attached to the batch (`where('sepa_batch_id', ...)`) with **no status predicate**. It inner-joins
  `lines` (line 111) and `member.paymentInformation` (line 112), so an invoice without lines or without a
  `payment_information` row silently disappears instead of being reported. There is no IBAN or amount filter:
  a cleared IBAN becomes `''` in `SepaExportInvoice` (`app/Domain/Billing/Payment/SepaExportInvoice.php:18`, a
  non-nullable `string`), which `digitick/sepa-xml` ships as an empty `<DbtrAcct>`.
- `app/Infrastructure/Billing/Payment/SepaExportServiceImpl.php:64-73` — one direct-debit payment-information block
  for the whole batch, `seqType` hardcoded to `PaymentInformation::S_RECURRING` (line 69). The loop (77-107) skips
  zero amounts, sends positive amounts as direct debits and negative amounts as credit transfers, both using the
  member's `payment_information` IBAN. Purchase orders go into the credit-transfer file (109-122).
- `app/Domain/Billing/Payment/SepaBatchServiceImpl.php:51-59` — `closeBatch` marks Open invoices Pending, writes
  bookkeeping records, sets the batch to Pending and dispatches `SepaBatchClosed`. No readiness check runs, and no
  close timestamp is stored.
- `app/Filament/Admin/Resources/SepaBatches/Pages/EditSepaBatch.php:127-137` — `closeBatch` only asks for
  confirmation. `exportSepa` (157-182) calls the exporter and zips the result with no gate of any kind;
  `markSubmitted` does not exist.
- `database/migrations/2026_05_19_193924_create_invoice_batches_table.php` plus the later migrations on
  `sepa_batches` give the table `invoice_date`, `sepa_transfer_date`, `status` and the three generation-progress
  columns. There is no `closed_at`, `exported_at`, `submitted_at` or `submitted_by`.
- `app/Console/Commands/GenerateSepaBatchCommand.php:19-29` → `app/Domain/Billing/Payment/SepaBatchGeneratorImpl.php:29-30`
  — the scheduled run creates the batch and attaches the month's invoices only. `CreateSepaBatch`
  (`app/Filament/Admin/Resources/SepaBatches/Pages/CreateSepaBatch.php:38-40`) calls
  `attachBatchMonthPurchaseOrders`, so the manual path sweeps purchase orders and the monthly scheduled path does not.
- `config/sepa.php` holds the four env-backed creditor values; `AppServiceProvider:27-32` maps them into the
  readonly `SepaConfiguration` (4 promoted properties). No notice period exists anywhere.
- `payment_information` (migration `2026_06_10_181456`) is one row per member with a **NOT NULL**
  `mandate_accepted_date`, soft deletes, and IBAN/BIC/holder. The mandate reference is derived
  (`app/Domain/Billing/Payment/MandateId.php`, `C{member:06d}-{paymentInformation:06d}`). There is no lifecycle
  state, no expiry and no `last_collected_at`.
- `app/Infrastructure/Billing/Invoices/InvoiceMailRepositoryDb.php:45` sets the mail's `sepaTransferDate` when the
  member has a mandate, so the invoice mail **is** the pre-notification. `resources/views/mail/invoice.blade.php:28-29`
  prints the collection date. `invoices.sent_at` is stamped by `markInvoiceAsSent` (line 53) after the queued send,
  which drifts with the queue — the stable business moment is the batch close.
- Existing tests: `tests/Feature/Infrastructure/Billing/Payment/SepaBatchRepositoryDbTest.php:242-278` builds the
  export invoice with `Invoice::factory()` **without a status**, so it currently passes because of the missing
  predicate; it must set `Pending`. `tests/Unit/Domain/Billing/Payment/SepaBatchServiceImplTest.php` and
  `SepaBatchServiceTest.php` are duplicate test classes for the same service; both must be updated if the service
  constructor or interface changes.

## Planned changes

| Change | Main files |
|---|---|
| Readiness model (reasons, categories, report, issue) and IBAN rule | `app/Domain/Billing/Payment/Readiness/**` |
| Readiness service with the mandate seam and notice period | `ExportReadinessServiceImpl`, `MandateReadinessService` + DB implementation |
| Collection read models and the export status predicate | `SepaBatchRepository`, `SepaBatchRepositoryDb` |
| Notice period in SEPA config | `config/sepa.php`, `SepaConfiguration`, `AppServiceProvider` |
| Export gate, confirmation and overrides | `SepaBatchService`, `SepaBatchServiceImpl`, `ExportNotReadyException` |
| Submission tracking columns, override table and models | two migrations, `SepaBatch`, `SepaExportOverride` |
| Filament: readiness report, close warning, export modal, mark submitted, editable incassodatum | `EditSepaBatch`, `SepaBatchForm`, readiness Blade view, `SepaBatchPolicy`, `BatchStatsOverview`, table |
| Scheduled purchase-order sweep | `SepaBatchGeneratorImpl` |
| Labels | `lang/nl/labels.php`, `lang/nl/notifications.php` |

## Readiness domain model

All of this lives in a new `Readiness` subfolder of the existing Billing/Payment context:
`app/Domain/Billing/Payment/Readiness/`.

`ExportReadinessReason` cases and their category. The category decides which section of the report an issue lands
in and whether the export can proceed:

```php
namespace App\Domain\Billing\Payment\Readiness;

enum ExportReadinessCategory: string
{
    case ManualPayerExpected = 'manual_payer_expected';
    case Overridable = 'overridable';
    case BrokenData = 'broken_data';
    case BatchLevel = 'batch_level';
}
```

```php
enum ExportReadinessReason: string
{
    case MissingMandate = 'missing_mandate';
    case RevokedMandate = 'revoked_mandate';
    case ExpiredMandate = 'expired_mandate';
    case MissingIban = 'missing_iban';
    case MalformedIban = 'malformed_iban';
    case ZeroAmount = 'zero_amount';
    case UncollectableStatus = 'uncollectable_status';
    case SequenceTypeMismatch = 'sequence_type_mismatch';
    case NoticePeriodTooShort = 'notice_period_too_short';

    public function category(): ExportReadinessCategory
    {
        return match ($this) {
            self::MissingMandate, self::RevokedMandate => ExportReadinessCategory::ManualPayerExpected,
            self::ExpiredMandate => ExportReadinessCategory::Overridable,
            self::NoticePeriodTooShort => ExportReadinessCategory::BatchLevel,
            self::MissingIban,
            self::MalformedIban,
            self::ZeroAmount,
            self::UncollectableStatus,
            self::SequenceTypeMismatch => ExportReadinessCategory::BrokenData,
        };
    }

    public function isOverridable(): bool
    {
        return $this === self::ExpiredMandate;
    }
}
```

`RevokedMandate` and `ExpiredMandate` are never produced until plan 04 lands. They are defined now so the gate, the
report and the UI do not change when the mandate model starts returning them.

One issue value object, also used for batch-level findings:

```php
final readonly class ExportReadinessIssue
{
    public function __construct(
        public ExportReadinessReason $reason,
        public ?InvoiceId $invoiceId = null,
        public ?string $invoiceNumber = null,
        public ?MemberId $memberId = null,
        public ?string $memberName = null,
        public ?CompoundPrice $amount = null,
        public ?string $detail = null,
    ) {}

    public static function forInvoice(
        ExportReadinessReason $reason,
        SepaCollectionCandidate $candidate,
        ?string $detail = null,
    ): self {
        return new self(
            reason: $reason,
            invoiceId: $candidate->invoiceId,
            invoiceNumber: $candidate->invoiceNumber,
            memberId: $candidate->memberId,
            memberName: $candidate->recipientName,
            amount: $candidate->total,
            detail: $detail,
        );
    }

    public static function forBatch(ExportReadinessReason $reason, string $detail): self
    {
        return new self(reason: $reason, detail: $detail);
    }

    public function category(): ExportReadinessCategory
    {
        return $this->reason->category();
    }
}
```

The report is the stable surface plan 06 extends. It keeps a single `issues` list; the category decides presentation,
so a new reason added by plan 06 needs no change to this class:

```php
final readonly class ExportReadinessReport
{
    private const BLOCKING_CATEGORIES = [ExportReadinessCategory::Overridable, ExportReadinessCategory::BrokenData, ExportReadinessCategory::BatchLevel];

    /** @param list<ExportReadinessIssue> $issues */
    public function __construct(
        public SepaBatchId $batchId,
        public array $issues,
        public int $noticePeriodDays,
    ) {}

    /** @return list<ExportReadinessIssue> */
    public function expectedExclusions(): array
    {
        return $this->withCategory(ExportReadinessCategory::ManualPayerExpected);
    }

    /** @return list<ExportReadinessIssue> */
    public function overridableIssues(): array
    {
        return $this->withCategory(ExportReadinessCategory::Overridable);
    }

    /** @return list<ExportReadinessIssue> */
    public function blockingIssues(): array
    {
        return array_values(array_filter(
            $this->issues,
            static fn (ExportReadinessIssue $issue): bool => in_array(
                $issue->category(),
                self::BLOCKING_CATEGORIES,
                true,
            ),
        ));
    }

    /** @return list<ExportReadinessIssue> */
    public function brokenDataIssues(): array
    {
        return $this->withCategory(ExportReadinessCategory::BrokenData);
    }

    /** @return list<ExportReadinessIssue> */
    public function batchLevelIssues(): array
    {
        return $this->withCategory(ExportReadinessCategory::BatchLevel);
    }

    public function isEmpty(): bool
    {
        return $this->issues === [];
    }

    public function isBlocked(): bool
    {
        return $this->blockingIssues() !== [];
    }

    public function requiresExclusionAcknowledgement(): bool
    {
        return $this->expectedExclusions() !== [];
    }

    public function isSatisfiedBy(ExportConfirmation $confirmation): bool
    {
        if ($this->requiresExclusionAcknowledgement() && !$confirmation->exclusionsAcknowledged) {
            return false;
        }

        return $this->unresolvedIssues($confirmation) === [];
    }

    /** @return list<ExportReadinessIssue> */
    public function unresolvedIssues(ExportConfirmation $confirmation): array
    {
        return array_values(array_filter(
            $this->blockingIssues(),
            static function (ExportReadinessIssue $issue) use ($confirmation): bool {
                if (!$issue->reason->isOverridable() || $issue->invoiceId === null) {
                    return true;
                }

                $override = $confirmation->overrideFor($issue->invoiceId);

                return $override === null
                    || $override->reason !== $issue->reason
                    || trim($override->note) === '';
            },
        ));
    }

    /** @return list<ExportReadinessIssue> */
    private function withCategory(ExportReadinessCategory $category): array
    {
        return array_values(array_filter(
            $this->issues,
            static fn (ExportReadinessIssue $issue): bool => $issue->category() === $category,
        ));
    }
}
```

The confirmation and its override travel from the Filament modal into the gate. Overrides are per export by
construction: the value object only lives for one call, and the repository appends a new audit row every time.

```php
final readonly class ExportOverride
{
    public function __construct(
        public InvoiceId $invoiceId,
        public ExportReadinessReason $reason,
        public string $note,
    ) {}
}
```

```php
final readonly class ExportConfirmation
{
    /** @param list<ExportOverride> $overrides */
    public function __construct(
        public bool $exclusionsAcknowledged,
        public array $overrides = [],
    ) {}

    public function overrideFor(InvoiceId $invoiceId): ?ExportOverride
    {
        foreach ($this->overrides as $override) {
            if ($override->invoiceId->value === $invoiceId->value) {
                return $override;
            }
        }

        return null;
    }
}
```

```php
final class ExportNotReadyException extends RuntimeException
{
    public function __construct(public readonly ExportReadinessReport $report)
    {
        parent::__construct('The SEPA batch is not ready for export.');
    }
}
```

## IBAN well-formedness

The domain may not use Laravel's validator, and `intervention/validation`'s `Iban` rule is a Laravel rule. Add a
small pure check in `app/Domain/Billing/Payment/Readiness/IbanFormat.php` with the ISO 13616 shape + mod-97 check:

```php
final readonly class IbanFormat
{
    public static function isWellFormed(?string $iban): bool
    {
        if ($iban === null) {
            return false;
        }

        $normalized = strtoupper(str_replace(' ', '', $iban));

        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $normalized) !== 1) {
            return false;
        }

        $rearranged = substr($normalized, 4) . substr($normalized, 0, 4);
        $digits = '';

        foreach (str_split($rearranged) as $character) {
            $digits .= ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
        }

        $remainder = 0;

        foreach (str_split($digits) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder === 1;
    }
}
```

The exporter is not changed: readiness guarantees no direct debit with an empty or malformed IBAN reaches it.

## Collection read models and the status predicate

Add two read models to the same `Readiness` namespace (`SepaCollectionCandidate`, `SepaBatchExportContext`; the
repository interface imports them). `SepaCollectionCandidate` is the pre-export view of an invoice; it deliberately
does not carry the mandate, because that comes from the mandate seam.

```php
final readonly class SepaCollectionCandidate
{
    public function __construct(
        public InvoiceId $invoiceId,
        public string $invoiceNumber,
        public string $recipientName,
        public ?MemberId $memberId,
        public CompoundPrice $total,
        public InvoiceStatus $status,
        public SequenceType $sequenceType,
    ) {}

    public function amountInCents(): int
    {
        return (int) round($this->total->price * 100);
    }

    public function isDebit(): bool
    {
        return $this->total->price > 0.0;
    }

    public function isRefund(): bool
    {
        return $this->total->price < 0.0;
    }
}
```

```php
final readonly class SepaBatchExportContext
{
    public function __construct(
        public SepaBatchStatus $status,
        public DateTimeInterface $sepaTransferDate,
        public ?DateTimeInterface $closedAt,
    ) {}
}
```

Extend `app/Domain/Billing/Payment/SepaBatchRepository.php`:

```php
/** @return list<SepaCollectionCandidate> */
public function getCollectionCandidates(SepaBatchId $batchId): array;

public function getExportContext(SepaBatchId $batchId): SepaBatchExportContext;

public function recordExport(
    SepaBatchId $batchId,
    ExportConfirmation $confirmation,
    ?int $exportedByUserId,
    DateTimeInterface $exportedAt,
): void;

public function markSubmitted(SepaBatchId $batchId, int $submittedByUserId, DateTimeInterface $submittedAt): void;
```

The status predicate is a one-line change in `getInvoicesForExport`. Use the qualified column because the query
joins `invoice_lines` and `members`:

```php
$invoices = Invoice::query()
    ->where('invoices.sepa_batch_id', $batchId->value)
    ->where('invoices.status', InvoiceStatus::Pending)
    ->joinRelationship('lines')
    ->joinRelationship('member.paymentInformation')
    // ...
```

`Pending` is the right value at export time: `markInvoicesAsPending` turns every Open invoice of the batch into
Pending when the batch closes, and the export action only exists for Pending batches.

The candidate query must include invoices the export query silently drops (no lines, no `payment_information`). Use
a left join so totals survive zero lines and do not require a payment-information row:

```php
/** @return list<SepaCollectionCandidate> */
#[Override]
public function getCollectionCandidates(SepaBatchId $batchId): array
{
    $rows = DB::table('invoices')
        ->leftJoin('invoice_lines', 'invoice_lines.invoice_id', '=', 'invoices.id')
        ->where('invoices.sepa_batch_id', $batchId->value)
        ->whereIn('invoices.status', [InvoiceStatus::Open, InvoiceStatus::Pending])
        ->groupBy(
            'invoices.id',
            'invoices.invoice_number',
            'invoices.recipient_name',
            'invoices.member_id',
            'invoices.status',
        )
        ->select(
            'invoices.id as invoice_id',
            'invoices.invoice_number',
            'invoices.recipient_name',
            'invoices.member_id',
            'invoices.status',
            DB::raw('COALESCE(SUM(invoice_lines.price * invoice_lines.quantity), 0) as total_price'),
            DB::raw('COALESCE(SUM(invoice_lines.vat * invoice_lines.quantity), 0) as total_vat'),
        )
        ->orderBy('invoices.id')
        ->get();

    return $rows->map(static fn (object $row): SepaCollectionCandidate => new SepaCollectionCandidate(
        invoiceId: InvoiceId::create((int) $row->invoice_id),
        invoiceNumber: $row->invoice_number,
        recipientName: $row->recipient_name,
        memberId: $row->member_id === null ? null : MemberId::create((int) $row->member_id),
        total: new CompoundPrice((float) $row->total_price, (float) $row->total_vat),
        status: InvoiceStatus::from($row->status),
        sequenceType: SequenceType::Recurring,
    ))->all();
}
```

Add `invoices.status` to the `groupBy` as well, because MySQL strict mode requires every selected non-aggregate.
`SequenceType::Recurring` is the honest value today: it mirrors the exporter's hardcoded `S_RECURRING` (see the
FRST/RCUR seam below).

```php
#[Override]
public function getExportContext(SepaBatchId $batchId): SepaBatchExportContext
{
    /** @var SepaBatch $batch */
    $batch = SepaBatch::findOrFail($batchId->value);

    return new SepaBatchExportContext(
        status: $batch->status,
        sepaTransferDate: $batch->sepa_transfer_date,
        closedAt: $batch->closed_at,
    );
}
```

`closeBatch` also stamps the close moment the notice period is measured from:

```php
#[Override]
public function closeBatch(SepaBatchId $batchId): void
{
    SepaBatch::query()
        ->where('id', $batchId->value)
        ->update([
            'status' => SepaBatchStatus::Pending,
            'closed_at' => now(),
        ]);
}
```

## Mandate readiness seam

This is the seam plan 04 fills. It is one interface, one value object and one infrastructure implementation that
reads today's `payment_information` rows; the gate only ever sees the value object.

```php
namespace App\Domain\Billing\Payment\Readiness;

enum MandateReadinessStatus: string
{
    case Active = 'active';
    case Missing = 'missing';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function isExpectedExclusion(): bool
    {
        return $this === self::Missing || $this === self::Revoked;
    }
}
```

```php
final readonly class MandateReadiness
{
    public function __construct(
        public MandateReadinessStatus $status,
        public ?string $iban = null,
        public ?string $bic = null,
        public ?MandateId $mandateId = null,
        public ?DateTimeInterface $signedOn = null,
        public SequenceType $expectedSequenceType = SequenceType::Recurring,
    ) {}

    public static function missing(): self
    {
        return new self(MandateReadinessStatus::Missing);
    }

    public function isUsable(): bool
    {
        return $this->status === MandateReadinessStatus::Active;
    }
}
```

```php
#[Autowire]
interface MandateReadinessService
{
    /**
     * @param list<MemberId> $memberIds
     * @return array<int, MandateReadiness> keyed by member id
     */
    public function forMembers(array $memberIds): array;
}
```

`app/Infrastructure/Billing/Payment/MandateReadinessServiceImpl.php` — today's source of truth. Soft-deleted rows are
excluded by the model's `SoftDeletes`, which is exactly today's "manual payer" state:

```php
final readonly class MandateReadinessServiceImpl implements MandateReadinessService
{
    /** @param list<MemberId> $memberIds */
    #[Override]
    public function forMembers(array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        $memberIdsAsInts = array_map(static fn (MemberId $id): int => $id->value, $memberIds);

        return PaymentInformation::query()
            ->whereIn('member_id', $memberIdsAsInts)
            ->get()
            ->mapWithKeys(static function (PaymentInformation $information): array {
                $memberId = (int) $information->getAttribute('member_id');

                return [
                    $memberId => new MandateReadiness(
                        status: MandateReadinessStatus::Active,
                        iban: (string) $information->getAttribute('banking_account_number'),
                        bic: (string) $information->getAttribute('banking_bic'),
                        mandateId: new MandateId(
                            MemberId::create($memberId),
                            PaymentInformationId::create((int) $information->getKey()),
                        ),
                        signedOn: $information->mandate_accepted_date,
                    ),
                ];
            })
            ->all();
    }
}
```

What plan 04 changes: only this implementation. It starts returning `Revoked` for revoked mandates (mapped to
`ManualPayerExpected`), `Expired` for 36-month-dormant mandates (mapped to `Overridable`), and keeps
`expectedSequenceType` at `Recurring` until plan 05 can make the exporter emit FRST. Plan 05 fills it from
`last_collected_at` together with the exporter and the candidate query, so the comparison cannot block a legitimate
first collection in between. `ExportReadinessServiceImpl`, the report, the Filament modal and the
gate stay untouched.

## FRST/RCUR sequence-type seam

Add the enum now, so plan 05 has a place to return a real value from and the exporter has a shared vocabulary:

```php
namespace App\Domain\Billing\Payment;

enum SequenceType: string
{
    case First = 'FRST';
    case Recurring = 'RCUR';
}
```

Today's chain is deliberately vacuous but wired end to end:

- `SepaCollectionCandidate::$sequenceType` is filled with `Recurring` by the repository, matching
  `SepaExportServiceImpl`'s hardcoded `PaymentInformation::S_RECURRING`.
- `MandateReadiness::$expectedSequenceType` defaults to `Recurring`, because no `last_collected_at` exists yet.
- `ExportReadinessServiceImpl` raises `SequenceTypeMismatch` when the two differ.

Plan 05 replaces the hardcoded exporter block with per-mandate payment-information blocks, fills
`SepaCollectionCandidate::$sequenceType` from the same mandate data, and makes the implementation return
`First`/`Recurring` from `last_collected_at`. The readiness comparison needs no change.

## Notice period

Add to `config/sepa.php` (plan 07 owns `config/billing.php`; the notice period is a SEPA export rule and belongs here):

```php
'notice_period_days' => (int) env('SEPA_NOTICE_PERIOD_DAYS', 14),
```

Extend `SepaConfiguration` with the promoted property and update every call site (the constructor ripple): the
binding in `AppServiceProvider`, `tests/Unit/Domain/Billing/Payment/SepaConfigurationTest.php`,
`tests/Unit/Domain/Billing/Invoices/Mails/InvoiceMailTest.php`,
`tests/Unit/Domain/Billing/Invoices/Jobs/SendInvoiceEmailTest.php` and
`tests/Feature/Infrastructure/Billing/Payment/SepaExportServiceImplTest.php`.

```php
$this->app->bind(SepaConfiguration::class, static fn () => new SepaConfiguration(
    creditorId: config('sepa.creditor_id') ?? '',
    creditorName: config('sepa.creditor_name') ?? '',
    creditorIban: config('sepa.creditor_iban') ?? '',
    creditorBic: config('sepa.creditor_bic') ?? '',
    noticePeriodDays: (int) config('sepa.notice_period_days'),
));
```

The mail moment is the batch's `closed_at`, because that is when the invoice mails are queued (the de facto
pre-notification). `invoices.sent_at` is not used: it is written after the queued send, drifts with the queue and is
cleared by a retry. A batch that is still Open has no `closed_at`; the assessment then uses the current clock value,
which is exactly the moment the close warning is shown.

```php
$mailDate = CarbonImmutable::instance($context->closedAt ?? $this->clock->now())->startOfDay();
$collectionDate = CarbonImmutable::instance($context->sepaTransferDate)->startOfDay();
$shortfall = $collectionDate->diffInDays($mailDate);

if ($collectionDate->lessThan($mailDate->addDays($this->configuration->noticePeriodDays))) {
    $issues[] = ExportReadinessIssue::forBatch(
        ExportReadinessReason::NoticePeriodTooShort,
        sprintf(
            '%d dagen tussen sluiting en incassodatum; minimaal %d.',
            max(0, $shortfall),
            $this->configuration->noticePeriodDays,
        ),
    );
}
```

Exactly `noticePeriodDays` between the two dates passes; one day less blocks.

## The readiness service

```php
#[Autowire]
interface ExportReadinessService
{
    public function assess(SepaBatchId $batchId): ExportReadinessReport;
}
```

```php
final readonly class ExportReadinessServiceImpl implements ExportReadinessService
{
    public function __construct(
        private SepaBatchRepository $batchRepository,
        private SepaConfiguration $configuration,
        private ClockInterface $clock,
        private MandateReadinessService $mandateReadiness,
    ) {}

    #[Override]
    public function assess(SepaBatchId $batchId): ExportReadinessReport
    {
        $context = $this->batchRepository->getExportContext($batchId);
        $candidates = $this->batchRepository->getCollectionCandidates($batchId);

        $issues = [];

        $mailDate = CarbonImmutable::instance($context->closedAt ?? $this->clock->now())->startOfDay();
        $collectionDate = CarbonImmutable::instance($context->sepaTransferDate)->startOfDay();

        if ($collectionDate->lessThan($mailDate->addDays($this->configuration->noticePeriodDays))) {
            $issues[] = ExportReadinessIssue::forBatch(
                ExportReadinessReason::NoticePeriodTooShort,
                sprintf(
                    '%d dagen tussen sluiting en incassodatum; minimaal %d.',
                    max(0, $collectionDate->diffInDays($mailDate)),
                    $this->configuration->noticePeriodDays,
                ),
            );
        }

        /** @var array<int, MemberId> $memberIds */
        $memberIds = [];

        foreach ($candidates as $candidate) {
            if ($candidate->memberId !== null) {
                $memberIds[$candidate->memberId->value] = $candidate->memberId;
            }
        }

        $mandates = $this->mandateReadiness->forMembers(array_values($memberIds));

        foreach ($candidates as $candidate) {
            $mandate = $candidate->memberId === null ? null : ($mandates[$candidate->memberId->value] ?? null);

            if ($context->status === SepaBatchStatus::Pending && $candidate->status === InvoiceStatus::Open) {
                $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::UncollectableStatus, $candidate);
                continue;
            }

            if ($candidate->amountInCents() === 0) {
                $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::ZeroAmount, $candidate);
                continue;
            }

            if ($candidate->isDebit()) {
                if ($mandate === null || $mandate->status === MandateReadinessStatus::Missing) {
                    $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::MissingMandate, $candidate);
                    continue;
                }

                if ($mandate->status === MandateReadinessStatus::Revoked) {
                    $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::RevokedMandate, $candidate);
                    continue;
                }

                if ($mandate->status === MandateReadinessStatus::Expired) {
                    $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::ExpiredMandate, $candidate);
                }

                if ($mandate->expectedSequenceType !== $candidate->sequenceType) {
                    $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::SequenceTypeMismatch, $candidate);
                }
            }

            $iban = $mandate?->iban;

            if ($iban === null || trim($iban) === '') {
                // A refund without an IBAN cannot be paid out. Plan 06 owns that row; until then it is not a field
                // the gate blocks on, otherwise every manual-payer refund would dead-lock the batch.
                if ($candidate->isRefund()) {
                    continue;
                }

                $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::MissingIban, $candidate);
                continue;
            }

            if (!IbanFormat::isWellFormed($iban)) {
                $issues[] = ExportReadinessIssue::forInvoice(ExportReadinessReason::MalformedIban, $candidate);
            }
        }

        return new ExportReadinessReport(
            batchId: $batchId,
            issues: $issues,
            noticePeriodDays: $this->configuration->noticePeriodDays,
        );
    }
}
```

Imports the service needs beyond its own namespace: `App\Domain\Billing\Invoices\InvoiceStatus`,
`App\Domain\Billing\Payment\SepaBatchRepository`, `App\Domain\Billing\Payment\SepaBatchStatus`,
`App\Domain\Billing\Payment\SepaConfiguration`, `App\Domain\Members\MemberId`, `Carbon\CarbonImmutable`,
`Psr\Clock\ClockInterface` and `Override`.

Semantics worth stating explicitly:

- Positive invoices need a usable mandate. Missing or revoked → expected manual-payer exclusion (not blocking).
  Expired → blocking but overridable. Sequence mismatch → broken data (not overridable).
- Negative invoices are refunds paid out by credit transfer. A present-but-malformed IBAN is broken data; a missing
  IBAN is left to plan 06, because the money is simply not paid out through the SEPA file and there is no in-app fix.
- A zero total is broken data for either sign. The exporter also skips zero amounts defensively, so an invoice that
  slips past the gate cannot corrupt the file.
- An `Open` invoice attached to a Pending batch is broken data (`UncollectableStatus`). It cannot happen through the
  UI, but without the rule the status predicate would drop it from the file while the gate reported everything clear.
- Invoices without a member (`member_id` null) or with no lines surface as `MissingIban` / `ZeroAmount`.

## Export gate

`app/Domain/Billing/Payment/SepaBatchService.php` gains:

```php
/**
 * @throws ExportNotReadyException
 */
public function export(SepaBatchId $batchId, ExportConfirmation $confirmation, ?int $exportedByUserId): SepaExport;

public function markSubmitted(SepaBatchId $batchId, int $submittedByUserId): void;
```

`SepaBatchServiceImpl` gains `SepaExportService`, `ExportReadinessService` and `ClockInterface`:

```php
public function __construct(
    private SepaBatchRepository $batchRepository,
    private BookkeepingRecordRepository $bookkeepingRepository,
    private Dispatcher $eventDispatcher,
    private SepaExportService $exportService,
    private ExportReadinessService $readinessService,
    private ClockInterface $clock,
) {}
```

```php
#[Override]
public function export(SepaBatchId $batchId, ExportConfirmation $confirmation, ?int $exportedByUserId): SepaExport
{
    if ($this->batchRepository->getExportContext($batchId)->status !== SepaBatchStatus::Pending) {
        throw new DomainException('Only a pending batch can be exported.');
    }

    $report = $this->readinessService->assess($batchId);

    if (!$report->isSatisfiedBy($confirmation)) {
        throw new ExportNotReadyException($report);
    }

    $export = $this->exportService->export($batchId);

    $this->batchRepository->recordExport($batchId, $confirmation, $exportedByUserId, $this->clock->now());

    return $export;
}

#[Override]
public function markSubmitted(SepaBatchId $batchId, int $submittedByUserId): void
{
    $this->batchRepository->markSubmitted($batchId, $submittedByUserId, $this->clock->now());
}
```

The gate lives in the batch lifecycle service, not in the Filament action, so a future caller cannot bypass it. The
exporter itself stays a pure XML builder and keeps its current unit tests unchanged. `SepaBatchServiceImpl` needs a
`DomainException` import; the readiness classes live next to it in the domain and need no container binding beyond
the two `#[Autowire]` interfaces.

## Persistence

### Batch tracking columns

`database/migrations/2026_10_22_100000_add_export_tracking_to_sepa_batches_table.php`:

```php
Schema::table('sepa_batches', static function (Blueprint $table): void {
    $table->timestamp('closed_at')->nullable()->after('status');
    $table->timestamp('exported_at')->nullable()->after('closed_at');
    $table->timestamp('submitted_at')->nullable()->after('exported_at');
    $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
});
```

```php
Schema::table('sepa_batches', static function (Blueprint $table): void {
    $table->dropForeign(['submitted_by']);
    $table->dropColumn(['closed_at', 'exported_at', 'submitted_at', 'submitted_by']);
});
```

### Override audit rows

One row per override per export, append-only. This is what makes the override "never sticky": a new export inserts a
new set, and an old reason can never silently satisfy a later export.

`database/migrations/2026_10_22_100001_create_sepa_export_overrides_table.php`:

```php
Schema::create('sepa_export_overrides', static function (Blueprint $table): void {
    $table->id();
    $table->foreignId('sepa_batch_id')->constrained()->cascadeOnDelete();
    $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
    $table->string('reason');
    $table->text('note');
    $table->timestamp('exported_at');
    $table->foreignId('exported_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['sepa_batch_id', 'exported_at']);
});
```

`app/Models/SepaExportOverride.php`:

```php
/** @property ExportReadinessReason $reason */
#[Guarded(['id', 'created_at', 'updated_at'])]
final class SepaExportOverride extends Model
{
    use HasFactory;

    /** @return BelongsTo<SepaBatch, $this> */
    public function sepaBatch(): BelongsTo
    {
        return $this->belongsTo(SepaBatch::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'reason' => ExportReadinessReason::class,
            'exported_at' => 'datetime',
        ];
    }
}
```

`SepaBatch` gains the columns in `#[Fillable]`, the casts (`closed_at`, `exported_at`, `submitted_at` as `datetime`),
the `submittedBy(): BelongsTo` and `exportOverrides(): HasMany` relations, and matching `@property` entries.

Repository implementation of the two write methods:

```php
#[Override]
public function recordExport(
    SepaBatchId $batchId,
    ExportConfirmation $confirmation,
    ?int $exportedByUserId,
    DateTimeInterface $exportedAt,
): void {
    DB::transaction(static function () use ($batchId, $confirmation, $exportedByUserId, $exportedAt): void {
        foreach ($confirmation->overrides as $override) {
            SepaExportOverride::query()->create([
                'sepa_batch_id' => $batchId->value,
                'invoice_id' => $override->invoiceId->value,
                'reason' => $override->reason,
                'note' => $override->note,
                'exported_at' => $exportedAt,
                'exported_by' => $exportedByUserId,
            ]);
        }

        SepaBatch::query()
            ->whereKey($batchId->value)
            ->update(['exported_at' => $exportedAt]);
    });
}

#[Override]
public function markSubmitted(SepaBatchId $batchId, int $submittedByUserId, DateTimeInterface $submittedAt): void
{
    SepaBatch::query()
        ->whereKey($batchId->value)
        ->whereNull('submitted_at')
        ->update([
            'submitted_at' => $submittedAt,
            'submitted_by' => $submittedByUserId,
        ]);
}
```

### Backfill

`database/migrations/2026_10_22_100002_backfill_closed_at_on_sepa_batches.php` sets `closed_at` from `updated_at`
for batches that are already Pending or Completed. Without it, a legacy Pending batch would have no mail moment and
the fallback (`now()`) would block its export. The value is a best-effort reconstruction; document that in the
migration comment.

## Filament

### Readiness Blade view

`resources/views/filament/admin/resources/sepa-batches/export-readiness.blade.php` renders the report as four
sections (batch level, broken data, overridable, expected exclusions), using the categories so plan 06 gets its own
section for free by adding a reason:

```blade
@php /** @var \App\Domain\Billing\Payment\Readiness\ExportReadinessReport $report */ @endphp

<div class="space-y-4 text-sm">
    @if ($report->isEmpty())
        <p class="text-success-600 dark:text-success-400">{{ __('labels.export_readiness.clear') }}</p>
    @else
        @foreach ([
            'batch_level' => $report->batchLevelIssues(),
            'broken_data' => $report->brokenDataIssues(),
            'overridable' => $report->overridableIssues(),
            'expected_exclusions' => $report->expectedExclusions(),
        ] as $section => $issues)
            @if ($issues !== [])
                <div>
                    <h4 class="font-semibold text-gray-950 dark:text-white">
                        {{ __('labels.export_readiness.' . $section) }}
                    </h4>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-gray-600 dark:text-gray-300">
                        @foreach ($issues as $issue)
                            <li>
                                @if ($issue->invoiceId)
                                    {{ $issue->invoiceNumber }} — {{ $issue->memberName }} —
                                    {{ $issue->amount }} —
                                @endif
                                {{ __('labels.export_readiness.reason.' . $issue->reason->value) }}
                                @if ($issue->detail)
                                    ({{ $issue->detail }})
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    @endif
</div>
```

The view iterates the report's category helpers, so plan 06 adds one section line and one category helper; the rest of
the view is unchanged.

### Editable incassodatum before export

The notice-period gate must have a recovery path: a batch closed with an incassodatum inside the 14-day window would
otherwise be permanently unexportable, because the form disables every field once the batch is no longer Open. Allow
editing `sepa_transfer_date` while the batch is Pending and has not been exported; `invoice_date` stays read-only after
close, and everything is read-only after the first export.

`app/Filament/Admin/Resources/SepaBatches/Schemas/SepaBatchForm.php` — replace the form-wide `->disabled(...)` with
per-field rules:

```php
DatePicker::make('invoice_date')
    ->label(__('labels.invoice_date'))
    ->native(false)
    ->format('d-m-Y')
    ->default(now()->format('d-m-Y'))
    ->required()
    ->disabled(static fn (?SepaBatch $record): bool =>
        $record !== null && $record->status !== SepaBatchStatus::Open),

DatePicker::make('sepa_transfer_date')
    ->label(__('labels.sepa_transfer_date'))
    ->native(false)
    ->format('d-m-Y')
    ->default(now()->addDays(14)->format('d-m-Y'))
    ->afterOrEqual('invoice_date')
    ->required()
    ->disabled(static fn (?SepaBatch $record): bool =>
        $record !== null
        && $record->status !== SepaBatchStatus::Open
        && !($record->status === SepaBatchStatus::Pending && $record->exported_at === null))
    ->helperText(static fn (?SepaBatch $record): ?string =>
        $record !== null && $record->status === SepaBatchStatus::Pending && $record->exported_at === null
            ? __('labels.sepa_transfer_date_pending_help')
            : null),
```

Remove the schema-level `->disabled(static fn (?SepaBatch $record) => ...)` so the per-field rules are the only gate.
The batch edit page is already permission-gated by `update_sepa_batches`.

Changing the date after close does not rewrite the mails that were already sent. The helper text tells finance to resend
the invoice mails through the existing per-invoice resend action (visible for Pending invoices), which renders the new
incassodatum from the batch at send time. The readiness report is recomputed on the next export, so the gate re-checks
the new date.

### EditSepaBatch header actions

Add a read-only report action first, available on Open and Pending batches:

```php
Action::make('readiness')
    ->label(__('labels.export_readiness.action'))
    ->icon('heroicon-m-shield-check')
    ->color('gray')
    ->visible(static fn (SepaBatch $record): bool => in_array(
        $record->status,
        [SepaBatchStatus::Open, SepaBatchStatus::Pending],
        true,
    ))
    ->modalHeading(__('labels.export_readiness.heading'))
    ->modalSubmitAction(false)
    ->modalCancelActionLabel(__('labels.close'))
    ->schema(static fn (SepaBatch $record, ExportReadinessService $readiness): array => [
        View::make('filament.admin.resources.sepa-batches.export-readiness')
            ->viewData(['report' => $readiness->assess(SepaBatchId::create($record->id))]),
    ]),
```

`closeBatch` keeps its confirmation but shows the report as the warning. Use the schema instead of
`requiresConfirmation()`, otherwise Filament renders two steps:

```php
Action::make('closeBatch')
    ->label(__('labels.close_batch'))
    ->icon('heroicon-m-lock-closed')
    ->color('warning')
    ->modalHeading(__('labels.close_batch'))
    ->modalDescription(__('labels.export_readiness.close_warning'))
    ->modalSubmitActionLabel(__('labels.close_batch'))
    ->schema(static fn (SepaBatch $record, ExportReadinessService $readiness): array => [
        View::make('filament.admin.resources.sepa-batches.export-readiness')
            ->viewData(['report' => $readiness->assess(SepaBatchId::create($record->id))]),
    ])
    ->action(static function (SepaBatch $record, SepaBatchService $batchService): void {
        $batchService->closeBatch(SepaBatchId::create($record->id));
    })
    ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refreshInvoicesTable'))
    ->successNotificationTitle(__('notifications.batch_closed'))
    ->visible(OnOpenSepaBatch::make(...)),
```

Closing stays a warning by decision: broken data does not prevent closing a batch, only exporting it.

The export action becomes the gate:

```php
Action::make('exportSepa')
    ->label(__('labels.export_sepa'))
    ->icon('heroicon-m-arrow-down-tray')
    ->color('info')
    ->visible(static fn (SepaBatch $record): bool => $record->status === SepaBatchStatus::Pending
        && (auth()->user()?->can('export', $record) ?? false))
    ->modalHeading(__('labels.export_readiness.export_heading'))
    ->schema(static function (SepaBatch $record, ExportReadinessService $readiness): array {
        $report = $readiness->assess(SepaBatchId::create($record->id));

        return [
            View::make('filament.admin.resources.sepa-batches.export-readiness')
                ->viewData(['report' => $report]),
            Checkbox::make('acknowledge_exclusions')
                ->label(__('labels.export_readiness.acknowledge_exclusions'))
                ->visible(static fn (): bool => $report->requiresExclusionAcknowledgement())
                ->accepted(static fn (): bool => $report->requiresExclusionAcknowledgement()),
            CheckboxList::make('overridden_invoices')
                ->label(__('labels.export_readiness.expired_mandate_overrides'))
                ->options(static fn (): array => collect($report->overridableIssues())
                    ->mapWithKeys(static fn (ExportReadinessIssue $issue): array => [
                        $issue->invoiceId->value => sprintf(
                            '%s — %s — %s',
                            $issue->invoiceNumber,
                            $issue->memberName,
                            $issue->amount,
                        ),
                    ])
                    ->all())
                ->visible(static fn (): bool => $report->overridableIssues() !== []),
            Textarea::make('override_reason')
                ->label(__('labels.export_readiness.override_reason'))
                ->required(static fn (Get $get): bool => filled($get('overridden_invoices')))
                ->visible(static fn (Get $get): bool => filled($get('overridden_invoices'))),
        ];
    })
    ->action(static function (
        SepaBatch $record,
        array $data,
        SepaBatchService $batchService,
        Action $action,
    ): ?Response {
        $confirmation = new ExportConfirmation(
            exclusionsAcknowledged: (bool) ($data['acknowledge_exclusions'] ?? false),
            overrides: array_map(
                static fn (int|string $invoiceId): ExportOverride => new ExportOverride(
                    invoiceId: InvoiceId::create((int) $invoiceId),
                    reason: ExportReadinessReason::ExpiredMandate,
                    note: (string) ($data['override_reason'] ?? ''),
                ),
                $data['overridden_invoices'] ?? [],
            ),
        );

        try {
            $xml = $batchService->export(
                SepaBatchId::create($record->id),
                $confirmation,
                is_int(auth()->id()) ? auth()->id() : null,
            );
        } catch (ExportNotReadyException) {
            $action->failure();

            return null;
        }

        $filename = 'sepa-batch-' . $record->id . '-' . $record->invoice_date->format('Y-m-d');
        $zip = new ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), $filename) . '.zip';
        $zip->open($tmp, ZipArchive::CREATE);
        if ($xml->directDebit) {
            $zip->addFromString('sepa-direct-debits.xml', $xml->directDebit);
        }
        if ($xml->creditTransfers) {
            $zip->addFromString('sepa-credit-transfers.xml', $xml->creditTransfers);
        }
        $zip->close();

        return response()->download($tmp, $filename . '.zip', ['Content-Type' => 'application/zip']);
    })
    ->failureNotificationTitle(__('notifications.sepa_export_blocked')),
```

The acknowledgement and override fields are visible only when relevant, but the closure still receives `$report` on
re-render. The reason textarea is required as soon as an override is selected, and the service enforces the same rule
again, so a crafted request cannot skip it.

Add the explicit submission action:

```php
Action::make('markSubmitted')
    ->label(__('labels.mark_submitted'))
    ->icon('heroicon-m-paper-airplane')
    ->color('success')
    ->requiresConfirmation()
    ->modalDescription(__('labels.mark_submitted_warning'))
    ->visible(static fn (SepaBatch $record): bool => $record->status === SepaBatchStatus::Pending
        && $record->exported_at !== null
        && $record->submitted_at === null
        && (auth()->user()?->can('markSubmitted', $record) ?? false))
    ->action(static function (SepaBatch $record, SepaBatchService $batchService): void {
        $userId = auth()->id();

        if (is_int($userId)) {
            $batchService->markSubmitted(SepaBatchId::create($record->id), $userId);
        }
    })
    ->successNotificationTitle(__('notifications.batch_submitted')),
```

Re-exporting after submission stays allowed per decision; it re-stamps `exported_at` and leaves `submitted_at` /
`submitted_by` as the record of the actual bank submission.

### Policy

Add to `app/Policies/SepaBatchPolicy.php`:

```php
public function export(User $user, Model $batch): bool
{
    Assert::isInstanceOf($batch, SepaBatch::class);

    return $user->can('update_sepa_batches') && $batch->status === SepaBatchStatus::Pending;
}

public function markSubmitted(User $user, Model $batch): bool
{
    Assert::isInstanceOf($batch, SepaBatch::class);

    return $user->can('update_sepa_batches')
        && $batch->status === SepaBatchStatus::Pending
        && $batch->exported_at !== null
        && $batch->submitted_at === null;
}
```

`update_sepa_batches` is held by the financial administration role; no new permission is introduced.

### Visibility of the stamps

- `BatchStatsOverview` gains two stats: `__('labels.exported_at')` and `__('labels.submitted_at')`, with
  `submittedBy.name` beside the latter, both falling back to `'—'`.
- `SepaBatchesTable` gains `exported_at` and `submitted_at` `TextColumn`s (`->dateTime()`, toggleable).

## Scheduled purchase-order sweep

`SepaBatchGeneratorImpl::generate` attaches the batch month's purchase orders, mirroring `CreateSepaBatch`:

```php
$batchId = $this->batchService->createBatch($sepaBatch->invoiceDate, $sepaBatch->sepaTransferDate);
$attachedCount = $this->batchService->attachBatchMonthInvoices($batchId);
$this->batchService->attachBatchMonthPurchaseOrders($batchId);
```

Placement is after the invoice attach and before `startGeneration`; the purchase-order count does not participate in
the invoice-generation expected count, so the progress widget is unaffected. No new query: the repository's
`addOpenPurchaseOrdersFromBatchMonth` already selects accepted Open purchase orders of the batch month, marks them
Pending and pins them to the batch.

## Labels

Add to `lang/nl/labels.php`:

```php
'exported_at' => 'Geëxporteerd op',
'submitted_at' => 'Ingediend bij de bank op',
'submitted_by' => 'Ingediend door',
'mark_submitted' => 'Markeer als ingediend',
'mark_submitted_warning' => 'Bevestig dat het SEPA-bestand bij de bank is ingediend. Dit kan niet worden teruggedraaid.',
'sepa_transfer_date_pending_help' => 'De incassodatum kan nog worden aangepast zolang de ronde niet is geëxporteerd. Verstuur de factuurmails opnieuw als u de datum wijzigt.',
'export_readiness' => [
    'action' => 'Exportcontrole',
    'heading' => 'Exportcontrole',
    'export_heading' => 'SEPA-bestand exporteren',
    'clear' => 'Geen aandachtspunten. De ronde kan worden geëxporteerd.',
    'close_warning' => 'Controleer de aandachtspunten hieronder. Ze blokkeren het sluiten niet, maar kunnen de export later tegenhouden.',
    'broken_data' => 'Actie nodig: gegevens onvolledig',
    'expected_exclusions' => 'Verwacht: handmatige betalers',
    'overridable' => 'Afwijkend: verlopen mandaat',
    'batch_level' => 'Actie nodig: incassodatum',
    'acknowledge_exclusions' => 'Ik heb de verwachte uitsluitingen gecontroleerd',
    'expired_mandate_overrides' => 'Toch incasseren op een verlopen mandaat',
    'override_reason' => 'Reden voor de afwijking',
    'reason' => [
        'missing_mandate' => 'Geen actief mandaat (handmatige betaler)',
        'revoked_mandate' => 'Mandaat ingetrokken (handmatige betaler)',
        'expired_mandate' => 'Mandaat is verlopen',
        'missing_iban' => 'Geen IBAN bekend',
        'malformed_iban' => 'IBAN is ongeldig',
        'zero_amount' => 'Factuurbedrag is nul',
        'uncollectable_status' => 'Factuur is nog niet vrijgegeven voor incasso',
        'sequence_type_mismatch' => 'Eerste/volgende incasso komt niet overeen met het mandaat',
        'notice_period_too_short' => 'Incassodatum ligt te dicht bij de factuurmail',
    ],
],
```

Add to `lang/nl/notifications.php`:

```php
'sepa_export_blocked' => 'Export geblokkeerd: los eerst de aandachtspunten op',
'batch_submitted' => 'SEPA-bestand gemarkeerd als ingediend',
```

## Tests

All tests are PHPUnit classes. Create the new test files with `./Taskfile artisan make:test --phpunit <name>`
(pass `--unit` for the domain tests) and the migrations with `./Taskfile artisan make:migration`. Use existing
factories and states, static PHPUnit assertions, and expectation classes for mocked collaborators. Every new
non-abstract class under `app/Domain` needs a matching `tests/Unit/**` file or `./Taskfile stan` fails with
`extrastan.UnitTestRequired` — the small value-object tests below exist for that reason as much as for behaviour.

### Unit — readiness value objects and report

`tests/Unit/Domain/Billing/Payment/Readiness/ExportReadinessReportTest.php`:

- `test_it_is_blocked_by_broken_data_issues`
- `test_it_is_not_blocked_by_expected_manual_payer_exclusions`
- `test_it_requires_acknowledgement_of_expected_exclusions`
- `test_it_requires_an_override_for_an_expired_mandate`
- `test_it_accepts_an_overridden_expired_mandate_with_a_reason`
- `test_it_rejects_an_override_without_a_reason`
- `test_it_rejects_an_override_with_the_wrong_reason`
- `test_it_separates_batch_level_issues_from_broken_data`
- `test_it_has_no_blockers_without_issues`

`tests/Unit/Domain/Billing/Payment/Readiness/ExportReadinessIssueTest.php`:

- `test_create_for_invoice_copies_the_invoice_facts`
- `test_create_for_batch_has_no_invoice`

`tests/Unit/Domain/Billing/Payment/Readiness/ExportConfirmationTest.php`:

- `test_override_for_finds_the_matching_invoice`
- `test_override_for_returns_null_when_absent`

`tests/Unit/Domain/Billing/Payment/Readiness/ExportOverrideTest.php`:

- `test_it_keeps_the_reason_and_note`

`tests/Unit/Domain/Billing/Payment/Readiness/ExportNotReadyExceptionTest.php`:

- `test_it_carries_the_report`

`tests/Unit/Domain/Billing/Payment/Readiness/MandateReadinessTest.php`:

- `test_active_mandate_is_usable`
- `test_missing_mandate_is_not_usable`
- `test_expected_sequence_type_defaults_to_recurring`
- `test_revoked_and_missing_are_expected_exclusions`

`tests/Unit/Domain/Billing/Payment/Readiness/SepaCollectionCandidateTest.php`:

- `test_amount_in_cents_rounds_the_price`
- `test_is_debit_and_is_refund_split_on_the_sign`

`tests/Unit/Domain/Billing/Payment/Readiness/SepaBatchExportContextTest.php`:

- `test_it_keeps_the_status_transfer_date_and_close_moment`

`tests/Unit/Domain/Billing/Payment/Readiness/IbanFormatTest.php`:

- `test_accepts_a_valid_dutch_iban`
- `test_accepts_an_iban_with_spaces_and_lowercase`
- `test_rejects_an_empty_iban`
- `test_rejects_a_too_short_iban`
- `test_rejects_an_invalid_checksum`
- `test_rejects_non_alphanumeric_characters`

`tests/Unit/Domain/Billing/Payment/Readiness/ExportReadinessReasonTest.php`:

- `test_every_reason_maps_to_a_category`
- `test_only_the_expired_mandate_is_overridable`

### Unit — readiness service

`tests/Unit/Domain/Billing/Payment/Readiness/ExportReadinessServiceImplTest.php`. Extend the existing
`Tests\Unit\Domain\Billing\Payment\SepaBatchRepositoryExpectation` with `expectsGetCollectionCandidates` and
`expectsGetExportContext`; create `MandateReadinessServiceExpectation` beside the test and use
`Tests\Unit\Domain\Clock\ClockExpectation` for `now()`.

- `test_assess_reports_a_manual_payer_as_an_expected_exclusion`
- `test_assess_reports_a_revoked_mandate_as_an_expected_exclusion`
- `test_assess_reports_an_expired_mandate_as_overridable`
- `test_assess_reports_a_missing_iban_as_broken_data`
- `test_assess_reports_a_malformed_iban_as_broken_data`
- `test_assess_reports_a_zero_total_as_broken_data`
- `test_assess_reports_an_open_invoice_in_a_pending_batch_as_broken_data`
- `test_assess_reports_a_sequence_type_mismatch_as_broken_data`
- `test_assess_passes_an_active_mandate_with_a_valid_iban`
- `test_assess_does_not_flag_a_refund_without_an_iban`
- `test_assess_reports_a_malformed_refund_iban`
- `test_assess_blocks_one_day_inside_the_notice_period`
- `test_assess_passes_exactly_on_the_notice_period_boundary`
- `test_assess_measures_from_the_close_moment_when_the_batch_is_closed`
- `test_assess_falls_back_to_the_clock_for_an_open_batch`
- `test_assess_returns_no_issues_for_an_empty_batch`

### Unit — batch service

Update both duplicate classes, `tests/Unit/Domain/Billing/Payment/SepaBatchServiceImplTest.php` and
`tests/Unit/Domain/Billing/Payment/SepaBatchServiceTest.php`, with the three new constructor dependencies plus new
`ExportReadinessServiceExpectation` and `SepaExportServiceExpectation` helper classes:

- `test_export_throws_when_readiness_is_not_satisfied`
- `test_export_does_not_generate_or_stamp_when_not_ready`
- `test_export_records_the_export_and_returns_the_files`
- `test_export_rejects_a_batch_that_is_not_pending`
- `test_mark_submitted_stamps_the_user_and_time`

Add `expectsRecordExport` and `expectsMarkSubmitted` to
`tests/Unit/Domain/Billing/Payment/SepaBatchRepositoryExpectation.php`, and
`expectsAttachBatchMonthPurchaseOrders` to `SepaBatchServiceExpectation`.

### Unit — generator and configuration

`tests/Unit/Domain/Billing/Payment/SepaBatchGeneratorImplTest.php`:

- add `expectsAttachBatchMonthPurchaseOrders` to both existing tests (the sweep is unconditional)
- `test_it_attaches_the_months_purchase_orders_before_starting_generation`

`tests/Unit/Domain/Billing/Payment/SepaConfigurationTest.php`:

- extend `test_values_are_stored_correctly` with `noticePeriodDays`
- `test_notice_period_defaults_to_fourteen_days` (construct through the container with the config value)

### Feature — repository

`tests/Feature/Infrastructure/Billing/Payment/SepaBatchRepositoryDbTest.php`:

- fix `test_get_invoices_for_export` by setting `status => InvoiceStatus::Pending` on the invoice
- `test_get_invoices_for_export_excludes_open_paid_and_declined_invoices`
- `test_get_collection_candidates_returns_open_and_pending_invoices_with_totals`
- `test_get_collection_candidates_includes_an_invoice_without_payment_information`
- `test_get_collection_candidates_returns_a_zero_total_for_an_invoice_without_lines`
- `test_get_collection_candidates_excludes_paid_and_declined_invoices`
- `test_get_export_context_returns_status_transfer_date_and_close_moment`
- `test_close_batch_stamps_closed_at`
- `test_record_export_stamps_exported_at_and_persists_the_override`
- `test_record_export_appends_a_new_override_on_every_export`
- `test_record_export_without_overrides_only_stamps_the_batch`
- `test_mark_submitted_stamps_the_submitting_user_and_time`
- `test_mark_submitted_does_not_overwrite_an_existing_submission`

### Feature — mandate seam

`tests/Feature/Infrastructure/Billing/Payment/MandateReadinessServiceImplTest.php`:

- `test_for_members_returns_an_active_mandate_with_iban_and_reference`
- `test_for_members_returns_missing_for_a_member_without_payment_information`
- `test_for_members_ignores_a_soft_deleted_mandate`
- `test_for_members_keys_the_result_by_member_id`
- `test_for_members_returns_an_empty_array_without_members`

### Feature — scheduled command

`tests/Feature/Console/GenerateSepaBatchCommandTest.php` (use `Bus::fake()` after creating the fixtures):

- `test_command_sweeps_the_batch_months_purchase_orders_into_the_batch`
- `test_command_ignores_purchase_orders_outside_the_batch_month`

### Feature — Filament

Extend `tests/Feature/Filament/SepaBatches/SepaBatchResourceTest.php`:

- `test_readiness_action_lists_expected_manual_payer_exclusions`
- `test_readiness_action_lists_broken_data`
- `test_close_batch_warns_but_still_closes_the_batch`
- `test_transfer_date_is_editable_on_a_pending_unexported_batch`
- `test_transfer_date_is_read_only_after_the_first_export`
- `test_invoice_date_is_read_only_after_close`
- `test_editing_the_transfer_date_changes_the_next_readiness_assessment`
- `test_export_action_is_hidden_without_update_permission`
- `test_export_action_is_blocked_by_broken_data`
- `test_export_action_requires_exclusion_acknowledgement`
- `test_export_action_requires_a_reason_for_an_expired_mandate_override` — bind a `MandateReadinessService` fake
  that returns `Expired` for the member, because today's implementation cannot produce that status
- `test_export_action_requires_the_override_again_on_a_second_export`
- `test_export_action_requires_exclusion_acknowledgement_on_every_export`
- `test_export_action_records_the_override_and_stamps_the_export`
- `test_export_action_re_stamps_exported_at_on_a_second_export`
- `test_mark_submitted_action_is_hidden_before_export`
- `test_mark_submitted_action_stamps_the_user_and_time`
- `test_mark_submitted_action_is_hidden_after_submission`
- `test_mark_submitted_action_is_hidden_without_update_permission`

For the successful export, assert the persisted stamps and (optionally) Livewire's `assertFileDownloaded()`; the
blocking paths assert `assertNotified(__('notifications.sepa_export_blocked'))` and a null `exported_at`, which is
the behaviour that matters.

## Hand-offs

- **Plan 04 — mandate lifecycle (built after this plan).** Replaces `MandateReadinessServiceImpl` internals only:
  the versioned mandate table supplies `Active` / `Revoked` / `Expired` and the IBAN. It keeps `expectedSequenceType`
  at `Recurring`; plan 05 makes the exporter, the candidate query and this value real together.
  `Revoked` is mapped to `ManualPayerExpected` and `Expired` to `Overridable`, both already handled by
  `ExportReadinessReason`; `ExportReadinessServiceImpl`, the report, the Blade view and the export gate are untouched.
  Do not build the mandate model here.
- **Plan 05 — FRST/RCUR.** `SequenceType` and the candidate/readiness comparison exist here. Plan 05 changes the
  exporter to emit two payment-information blocks and fills `SepaCollectionCandidate::$sequenceType` and
  `MandateReadiness::$expectedSequenceType` from `last_collected_at`. No gate change.
- **Plan 06 — manual payers.** Extends `ExportReadinessReason` with its refund-without-IBAN case and category, adds
  the matching category helper and one section line to the report view; the report's `issues` list and `category()`
  mapping are the stable extension points. It also owns the derived manual-payer state and the refund IBAN defect.
  This plan deliberately reports a refund with a *present but malformed* IBAN as broken data and does not flag a
  refund with no IBAN.
- **Plan 07 — due date and `config/billing.php`.** This plan does not touch `config/billing.php`; the SEPA notice
  period lives in `config/sepa.php` as `notice_period_days`.
- **Plan 01 — declined-payment recovery.** Not a prerequisite. The status predicate uses `Pending`, which is where a
  reopened/retried invoice ends up once its new batch closes. The export readiness candidate query also includes
  `Open` so the close-time warning sees the invoices the close is about to make Pending.

## Out of scope

- The versioned mandate model, revocation, the derived 36-month expiry and `last_collected_at` — plan 04.
- FRST/RCUR generation and the two-payment-information-block export — plan 05.
- The derived "manual payer" member state, the due date, the refund IBAN fix and the refund-without-IBAN resolution
  flow — plan 06.
- `invoices.due_date`, aging, reminders and `config/billing.php` — plan 07 (and later).
- Uploading the file to the bank. Submission stays a manual act; this plan only records it.
- Blocking on purchase-order readiness. Creditor name and IBAN are already enforced before a purchase order can
  become Pending, so the credit-transfer file has no equivalent gate to add here.
- An export history table. `sepa_batches.exported_at` is the latest export; the override rows are the audit trail of
  the decisions attached to it.
- Notifying finance when a batch is submitted, and changing `invoices.sent_at` semantics.

## Open questions

1. **Which role may mark a batch submitted.** Gap 5 only says "explicit action". *Recommended resolution:*
   `update_sepa_batches`, which the financial administration role already holds, matching export. Revisit only if a
   second-approval step is wanted later.

## Verification

```bash
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/Readiness/ExportReadinessReportTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/Readiness/ExportReadinessIssueTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/Readiness/ExportConfirmationTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/Readiness/ExportOverrideTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/Readiness/ExportNotReadyExceptionTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/Readiness/ExportReadinessReasonTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/Readiness/ExportReadinessServiceImplTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/Readiness/IbanFormatTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/Readiness/MandateReadinessTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/Readiness/SepaCollectionCandidateTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/Readiness/SepaBatchExportContextTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/SepaBatchServiceImplTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/SepaBatchServiceTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/SepaBatchGeneratorImplTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/SepaConfigurationTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Payment/SepaBatchRepositoryDbTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Payment/MandateReadinessServiceImplTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Payment/SepaExportServiceImplTest.php
./Taskfile artisan test --compact tests/Feature/Console/GenerateSepaBatchCommandTest.php
./Taskfile artisan test --compact tests/Feature/Filament/SepaBatches/SepaBatchResourceTest.php
./Taskfile stan
./Taskfile lint
```
