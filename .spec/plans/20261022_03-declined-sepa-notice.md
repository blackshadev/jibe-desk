# Declined-SEPA-payment notice — implementation plan

Date: 2026-10-22
Backlog step: 3 of 13 in [Prioritize the confirmed backlog and decide the plan split](../maps/20261021-missing-features/tickets/07-prioritize-confirmed-backlog-and-decide-plan-split.md)
Decisions source: [Judge communication & reminders candidates](../maps/20261021-missing-features/tickets/06-judge-communication-reminders-candidates.md), confirmed gap 2

> **Prerequisite:** [Declined-payment recovery](20261022_01-declined-payment-recovery.md) (backlog step 1) must land
> first. It adds `App\Domain\Billing\Invoices\Events\InvoicePaymentDeclined`, emits it once per invoice that actually
> transitions to `Declined` in `InvoiceServiceImpl::markAsDeclined()`, and gives that method the
> `(InvoiceIdList $ids, ?BankTransactionId $reversalTransactionId = null, ?string $note = null)` signature used by both
> the automatic reversal and the manual admin decline.
>
> This plan does not modify that event or the decline funnel. It adds the member-facing mail that consumes it.

## Overview

Send the member one email when their direct debit is declined: the debit failed, the amount has been reversed, and
here is how to pay. The mail reuses the invoice mail's transfer-instruction block (creditor IBAN, invoice number,
amount) and carries a re-authorisation hint when the member's mandate is revoked or expired.

Both decline paths (automatic SEPA reversal and manual admin decline) go through the same single funnel, so one listener
on `InvoicePaymentDeclined` covers both. The mail is resolved and queued through the existing
event → listener → queued job → `MailSender` pipeline and is therefore recorded in `outgoing_emails` like every other
mail.

The mail states no cause. Invoices store no decline reason (unlike purchase orders) and no bank return code reaches the
database, so the template must not imply one. There is no admin copy: the panel already shows the declined invoice and
the export-readiness report covers follow-up.

## Current situation

- **Both decline paths already converge.** `InvoiceServiceImpl::markAsDeclined()` (`app/Domain/Billing/Invoices/InvoiceServiceImpl.php:33-37`)
  only delegates to the repository today. Plan 01 turns it into the funnel that dispatches `InvoicePaymentDeclined` for
  every transitioned id. Its callers are `BankTransactionServiceImpl::linkReversal()` (automatic) and the
  `InvoiceStateActions` manual action.
- **The current repository transition is `Pending → Declined` only.** `InvoiceRepositoryDb::markAsDeclined()`
  (`app/Infrastructure/Billing/Invoices/InvoiceRepositoryDb.php:181-188`) filters on `status = Pending`; plan 01 changes
  its return type to the ids it actually transitioned and records decline history.
- **No cause is available.** `InvoiceStatus` has only `Open`, `Pending`, `Paid`, `Declined`
  (`app/Domain/Billing/Invoices/InvoiceStatus.php`). `invoices` has no reason column; `declined_reason` exists only on
  purchase orders (`database/migrations/2026_09_24_091648_add_declined_reason_to_purchase_orders_table.php:14`). The
  MT940 import discards `/RTRN/`, so no return code is stored either.
- **The invoice mail already renders the transfer-instruction branch.** `resources/views/mail/invoice.blade.php:26-34`
  has three branches: credit payout, direct debit with an incassodatum, and manual transfer with creditor IBAN,
  account name and invoice number. The last branch is the block this plan must reuse, but it is inline and duplicated
  nowhere else yet.
- **The invoice read model and Mailable are line-oriented.** `InvoiceMailRepositoryDb::getInvoiceMailData()`
  (`app/Infrastructure/Billing/Invoices/InvoiceMailRepositoryDb.php:20-48`) loads lines, member, payment information
  and the SEPA batch, and builds `InvoiceMailData` with address, lines and `sepaTransferDate`. `InvoiceMail`
  (`app/Domain/Billing/Invoices/Mails/InvoiceMail.php:30-50`) renders those into `mail.invoice`. The notice needs none
  of the lines/address/incassodatum, so reusing `InvoiceMailData` would force irrelevant data into the mail.
- **Recipient data is a snapshot on the invoice.** `invoices.recipient_email`, `recipient_name` and
  `recipient_address` are non-nullable strings (`database/migrations/2026_05_15_073548_create_invoices_table.php:17-19`).
  `member_id` is nullable and `nullOnDelete`, so an invoice can outlive its member. Delivery should therefore use the
  snapshot, exactly as `InvoiceMailRepositoryDb` does at line 39; a shared household email is naturally the snapshot
  address too.
- **The pipeline is event-driven and queued.** `bootstrap/app.php:16-18` discovers listeners under `app/Domain`.
  `QueueInvoiceEmails` (`app/Domain/Billing/Invoices/Listeners/QueueInvoiceEmails.php:24-38`) shows the
  event → listener → job pattern; `SendInvoiceEmail` (`app/Domain/Billing/Invoices/Jobs/SendInvoiceEmail.php:22-37`)
  resolves mail data in the job and calls `MailSender`. `LaravelMailSender`
  (`app/Infrastructure/Mail/LaravelMailSender.php:15-22`) wraps the domain mail in `MailMailable`, which adds the
  `X-Mailable-Class` header (`app/Infrastructure/Mail/MailMailable.php:30-51`). `LogOutgoingMail`
  (`app/Domain/Mail/Listeners/LogOutgoingMail.php:25-56`) writes the `outgoing_emails` row on `MessageSending`.
- **The audit row stores less than the headers suggest.** `OutgoingEmailRepositoryDb::queue()`
  (`app/Infrastructure/Mail/OutgoingEmailRepositoryDb.php:16-31`) persists tracking id, mailable class, recipient,
  subject, `member_id` and `queued_at`. It never writes `related_model_type`/`related_model_id` or `batch_id`, even
  though `MailMailable` adds the related headers. Assertions must not expect those columns.
- **Member attribution is email-based.** `LogOutgoingMail` resolves the member with
  `MemberRepository::getByEmail()`; a shared household address resolves to the first match. That is display-only and
  does not affect who receives the mail.
- **Mailbook previews all five templates.** `routes/mailbook.php:58-148` registers `InvoiceMail` with three variants,
  including the manual-transfer and credit branches, so the shared block can be checked visually there.
- **No mail mentions a decline.** The gap inventory (`.spec/questions/20261021_communication-reminders-gap-inventory.md`,
  section 3) confirms no Mailable references a decline and `markAsDeclined()` only updates the repository.
- **Tests run the queue synchronously and mail through the array transport** (`phpunit.xml`: `QUEUE_CONNECTION=sync`,
  `MAIL_MAILER=array`), so a listener can be exercised end to end in a feature test. Unit tests for mail use
  `MailSenderExpectation` (`tests/Unit/Domain/Mail/MailSenderExpectation.php`), and Filament tests use `Mail::fake()`
  with `Mail::assertQueued(MailMailable::class)` (for example
  `tests/Feature/Filament/Admin/Resources/Members/AcceptMemberActionTest.php:44-61`).
- **Payment information has no mandate lifecycle yet.** `payment_information` stores `mandate_accepted_date` and bank
  fields; there is no revoked/expired state. The versioned mandate model is backlog step 4 and lands after this plan.
  The hint therefore needs a view-data seam whose source step 4 supplies; until then it resolves `false`.

## Planned changes

| Change | Main files |
|---|---|
| Add a narrow read model for the declined notice with the safety guards | `InvoiceMailRepository`, `InvoiceMailRepositoryDb`, new `InvoicePaymentDeclinedMailData` |
| Add the member-facing Mailable | new `Mails/InvoicePaymentDeclinedMail` |
| React to the domain event and queue one job per declined invoice | new `Listeners/SendInvoicePaymentDeclinedNotice` |
| Send through the existing pipeline in a queued job | new `Jobs/SendInvoicePaymentDeclinedEmail` |
| Extract the invoice mail's transfer-instruction block into a partial | new `resources/views/mail/partials/transfer-instructions.blade.php`, `resources/views/mail/invoice.blade.php` |
| Add the declined-notice template and its Dutch copy | new `resources/views/mail/invoice-payment-declined.blade.php` |
| Register the template in Mailbook with both hint states | `routes/mailbook.php` |
| Cover the flow with unit, repository, scenario and template tests | `tests/Unit/...`, `tests/Feature/...` |

No migration, config or dependency change is required.

## Declined-notice read model

Add a small DTO rather than stretching `InvoiceMailData`:

`app/Domain/Billing/Invoices/InvoicePaymentDeclinedMailData.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

use App\Domain\Mail\Recipient;
use App\Domain\SharedKernel\CompoundPrice;
use DateTimeInterface;

final readonly class InvoicePaymentDeclinedMailData
{
    public function __construct(
        public int $invoiceId,
        public string $invoiceNumber,
        public Recipient $recipient,
        public DateTimeInterface $invoiceDate,
        public CompoundPrice $total,
        public bool $mandateNeedsReauthorisation,
    ) {}
}
```

Extend `app/Domain/Billing/Invoices/InvoiceMailRepository.php`:

```php
/**
 * Returns null when the invoice no longer exists, is no longer Declined,
 * is a credit invoice, or has no usable recipient address.
 */
public function getInvoicePaymentDeclinedMailData(
    InvoiceId $id,
): ?InvoicePaymentDeclinedMailData;
```

Implement it in `app/Infrastructure/Billing/Invoices/InvoiceMailRepositoryDb.php` (add `InvoiceStatus` to the imports):

```php
#[Override]
public function getInvoicePaymentDeclinedMailData(InvoiceId $id): ?InvoicePaymentDeclinedMailData
{
    /** @var Invoice|null $invoice */
    $invoice = Invoice::query()
        ->with('lines')
        ->find($id->value);

    if ($invoice === null || $invoice->status !== InvoiceStatus::Declined) {
        return null;
    }

    if ($invoice->credit_invoice_id !== null || $invoice->creditInvoice()->exists()) {
        return null;
    }

    if (trim($invoice->recipient_email) === '') {
        return null;
    }

    return new InvoicePaymentDeclinedMailData(
        invoiceId: $invoice->id,
        invoiceNumber: $invoice->invoice_number,
        recipient: new Recipient($invoice->recipient_name, $invoice->recipient_email),
        invoiceDate: $invoice->date,
        total: $invoice->total,
        // Plan 04 replaces this literal with the versioned-mandate revoked/expired check.
        mandateNeedsReauthorisation: false,
    );
}
```

Why each guard lives here:

- **Gone or no longer declined** — the job runs after the event and can race an admin reopen (plan 01 adds reopen and
  retry). A reopened or paid invoice must not receive a stale decline notice.
- **Credit invoice** — both directions are excluded, matching plan 01's reopen guards: the invoice itself references an
  original (`credit_invoice_id`), or it has already been credited (`creditInvoice`). A credit note is a payout, not a
  collection, and must never produce a failed-debit mail.
- **Blank recipient** — `recipient_email` is non-nullable but can be an empty string (for example a member with no
  usable address). The snapshot is authoritative; do not fall back to another household member or to the current
  `members.email`, because that would deliver invoice correspondence to an address the invoice was never sent to.
  Shared household addresses need no special handling: each invoice carries the shared address and gets one notice.
- **`$invoice->total`** uses the model accessor and the eager-loaded lines, exactly like the invoice mail read model.

The mandate flag is deliberately supplied here, not in the template. Plan 04 replaces the literal with its mandate
query; the Mailable, job, listener and template keep the same contract (see **Hand-offs**).

## The Mailable

`app/Domain/Billing/Invoices/Mails/InvoicePaymentDeclinedMail.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices\Mails;

use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailData;
use App\Domain\Billing\Payment\SepaConfiguration;
use App\Domain\Mail\BaseMail;
use App\Domain\Mail\Recipient;
use App\Domain\Mail\Related;
/** @phpstan-ignore domain.dependency */
use App\Models\Invoice;
use Illuminate\Mail\Mailables\Content;
use Override;

final readonly class InvoicePaymentDeclinedMail extends BaseMail
{
    public function __construct(
        public InvoicePaymentDeclinedMailData $invoice,
        public SepaConfiguration $sepaConfiguration,
    ) {}

    #[Override]
    public function related(): Related
    {
        return new Related(Invoice::class, $this->invoice->invoiceId);
    }

    #[Override]
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice-payment-declined',
            with: [
                'memberName' => $this->invoice->recipient->name,
                'invoiceNumber' => $this->invoice->invoiceNumber,
                'invoiceDate' => $this->invoice->invoiceDate,
                'total' => $this->invoice->total,
                'creditorIban' => $this->sepaConfiguration->creditorIban,
                'creditorAccountName' => $this->sepaConfiguration->creditorName,
                'mandateNeedsReauthorisation' => $this->invoice->mandateNeedsReauthorisation,
            ],
        );
    }

    #[Override]
    public function subject(): string
    {
        return 'Betaling van factuur ' . $this->invoice->invoiceNumber . ' is niet gelukt';
    }

    #[Override]
    public function to(): Recipient
    {
        return $this->invoice->recipient;
    }
}
```

The `@phpstan-ignore domain.dependency` comment sits on the `use App\Models\Invoice;` import line, as required by the
project's PHPStan convention (see `CODING_STANDARDS.md` and `InvoiceMail.php:12`). `related()` points at the invoice
so the mail keeps the same audit semantics as the invoice mail.

## The event listener

`../../app/Domain/Billing/Invoices/Listeners/QueueInvoicePaymentDeclinedEmail.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices\Listeners;

use App\Domain\Billing\Invoices\Events\InvoicePaymentDeclined;
use App\Domain\Billing\Invoices\InvoiceMailRepository;
use App\Domain\Billing\Invoices\Jobs\SendInvoicePaymentDeclinedEmail;
use App\Domain\Jobs\JobDispatcher;

final readonly class SendInvoicePaymentDeclinedNotice
{
    public function __construct(
        private InvoiceMailRepository $mailRepository,
        private JobDispatcher $dispatcher,
    ) {}

    public function handle(InvoicePaymentDeclined $event): void
    {
        if ($this->mailRepository->getInvoicePaymentDeclinedMailData($event->invoiceId) === null) {
            return;
        }

        $this->dispatcher->dispatch(new SendInvoicePaymentDeclinedEmail($event->invoiceId));
    }
}
```

No provider registration is needed: `bootstrap/app.php` discovers `handle()` methods under `app/Domain`. The listener
resolves invoice and recipient through the read model before enqueueing, so an invoice that is gone, credited, no
longer declined or has no address never produces a queued job. The job resolves again when it runs; the second read is
what protects against a reopen between the decline and the queue worker (see **Avoid double-sending**).

## The queued job

`app/Domain/Billing/Invoices/Jobs/SendInvoicePaymentDeclinedEmail.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices\Jobs;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceMailRepository;
use App\Domain\Billing\Invoices\Mails\InvoicePaymentDeclinedMail;
use App\Domain\Billing\Payment\SepaConfiguration;
use App\Domain\Jobs\BaseJob;
use App\Domain\Mail\MailSender;

final class SendInvoicePaymentDeclinedEmail extends BaseJob
{
    public function __construct(
        public readonly InvoiceId $invoiceId,
    ) {}

    public function handle(
        InvoiceMailRepository $repository,
        SepaConfiguration $configuration,
        MailSender $mailSender,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $mailData = $repository->getInvoicePaymentDeclinedMailData($this->invoiceId);

        if ($mailData === null) {
            return;
        }

        $mailSender->send(new InvoicePaymentDeclinedMail($mailData, $configuration));
    }
}
```

The job mirrors `SendInvoiceEmail` (batch guard, resolve, send) and `SendSepaBatchCreatedEmail` (job takes the id, not
a hydrated graph). `BaseJob` already supplies three tries and queueing. `MailSender::send()` wraps the domain mail in a
queued `MailMailable`, so delivery stays asynchronous even though this domain job itself is dispatched from a listener.

## Extract the transfer-instruction block

Add `resources/views/mail/partials/transfer-instructions.blade.php`:

```blade
Het bedrag van **{{ $amount }}** dient u over te maken naar rekeningnummer **{{ $creditorIban }}**
ten name van **{{ $creditorAccountName }}**
onder vermelding van **factuurnummer {{ $invoiceNumber }}**.
```

Replace the manual-transfer branch in `resources/views/mail/invoice.blade.php` (currently lines 30–34) with the shared
include, so the invoice mail and the notice cannot drift apart:

```blade
@else
@include('mail.partials.transfer-instructions', [
    'amount' => $total,
    'creditorIban' => $creditorIban,
    'creditorAccountName' => $creditorAccountName,
    'invoiceNumber' => $invoiceNumber,
])
@endif
```

This is a wording-preserving extraction; the only textual addition is "Het bedrag van **€ …**", which the existing
total line already implies and the notice needs explicitly. The credit and direct-debit branches stay untouched.

## The declined-notice template

`resources/views/mail/invoice-payment-declined.blade.php`

```blade
@component('mail::message')

Beste {{ $memberName }},

De automatische incasso van factuur {{ $invoiceNumber }} van {{ $invoiceDate->format('d-m-Y') }} is niet gelukt. Het bedrag van {{ $total }} is van uw rekening teruggeboekt.

**Wat kunt u doen?**

@include('mail.partials.transfer-instructions', [
    'amount' => $total,
    'creditorIban' => $creditorIban,
    'creditorAccountName' => $creditorAccountName,
    'invoiceNumber' => $invoiceNumber,
])

@if ($mandateNeedsReauthorisation)
Uw machtiging voor automatische incasso is ingetrokken of verlopen. Neem contact op met de ledenadministratie om een nieuwe machtiging af te geven. Zodra wij die hebben ontvangen, kunnen wij het bedrag opnieuw incasseren.
@else
Wilt u het bedrag liever opnieuw laten incasseren? Neem dan contact op met de ledenadministratie.
@endif

Met vriendelijke groet,<br>
Almere Centraal
@endcomponent
```

Copy rules that the template must keep:

- It names the failure ("is niet gelukt") and the reversal ("is van uw rekening teruggeboekt") without a cause.
  No bank code, no "onvoldoende saldo", no reason placeholder — the data does not exist.
- The amount, creditor IBAN, account name and invoice number come from the shared partial, so the payment reference is
  identical to the invoice mail.
- The re-authorisation paragraph appears only when `mandateNeedsReauthorisation` is true. The else-branch still gives
  the member a route to re-collection, which is honest for a healthy mandate.
- The tone is formal ("u"), matching `registration-received`, `member-welcome` and `invoice`.

## Mailbook registration

Add two variants to `routes/mailbook.php` beside the existing `InvoiceMail` registrations. Import
`App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailData` and
`App\Domain\Billing\Invoices\Mails\InvoicePaymentDeclinedMail`.

```php
Mailbook::add(InvoicePaymentDeclinedMail::class)
    ->variant('Declined payment', static function (SepaConfiguration $config): Mailable {
        return new MailMailable(new InvoicePaymentDeclinedMail(
            new InvoicePaymentDeclinedMailData(
                invoiceId: 3,
                invoiceNumber: '1234',
                recipient: new Recipient('Jan de Vries', 'jan@devries.nl'),
                invoiceDate: new DateTimeImmutable('2024-01-01'),
                total: new CompoundPrice(120, 25.2),
                mandateNeedsReauthorisation: false,
            ),
            $config,
        ));
    })
    ->variant('Declined payment - re-authorise mandate', static function (SepaConfiguration $config): Mailable {
        return new MailMailable(new InvoicePaymentDeclinedMail(
            new InvoicePaymentDeclinedMailData(
                invoiceId: 3,
                invoiceNumber: '1234',
                recipient: new Recipient('Jan de Vries', 'jan@devries.nl'),
                invoiceDate: new DateTimeImmutable('2024-01-01'),
                total: new CompoundPrice(120, 25.2),
                mandateNeedsReauthorisation: true,
            ),
            $config,
        ));
    });
```

Both states are visible in the dev-only Mailbook route, including the shared partial. The existing `InvoiceMail`
variants must still render after the extraction.

## Lang keys and Dutch copy

No entries are added to `lang/nl`. None of the five existing mails uses translation keys: subjects are literal Dutch
strings in the Mailable classes (`InvoiceMail::subject()` at line 55, `MemberWelcome::subject()` at line 36) and bodies
are literal Dutch in `resources/views/mail/*.blade.php`. Introducing `lang/nl/mails.php` for one mail would create a
second convention for no functional gain. The concrete copy is therefore the literal subject above and the template
body in the previous section; tests assert that copy directly.

If the project later moves mail copy into translation files, this mail moves with the rest; nothing in its data
contract depends on where the strings live.

## Avoid double-sending per decline

There is no new marker column and no dedupe state. The guarantee comes from plan 01's contract:

- The repository returns only the ids it actually transitioned from `Pending` to `Declined`, and
  `InvoiceServiceImpl::markAsDeclined()` dispatches one event per returned id. A second decline call on an
  already-declined invoice transitions nothing and emits nothing.
- One event → one listener invocation → one queued job → one mail.
- The job re-reads the invoice before sending. If the admin reopened it between the event and the worker, the read
  model returns `null` and no mail is sent.
- If the invoice is declined again after a reopen, that is a new transition and therefore a new notice, which is
  correct.

## Hand-offs

- **From plan 01 (required):** `InvoicePaymentDeclined` in `app/Domain/Billing/Invoices/Events/`, dispatched from the
  single `markAsDeclined()` funnel after the repository transaction. This plan assumes the event carries only
  `InvoiceId` and that the repository excludes non-transitioning invoices. If plan 01's event gains fields, the listener
  can ignore them; the read model remains the source of truth.
- **To plan 04 (mandate lifecycle):** the only required change is the `mandateNeedsReauthorisation: false` literal in
  `InvoiceMailRepositoryDb::getInvoicePaymentDeclinedMailData()`. Plan 04 replaces it with the versioned-mandate check
  for the invoice's member (revoked or expired → `true`; no mandate, active mandate, or null `member_id` → `false`).
  The DTO field name and meaning are the contract: "this member's mandate for the invoice's collection is revoked or
  expired, so a new authorisation is required before another collection". The Mailable, template, listener, job and
  Mailbook variants need no change. If plan 04's query needs the member and payment information, add them to the
  repository's eager load at that point; do not put the query in the template.
- **To plan 08 (payment reminders):** that plan may reuse
  `mail/partials/transfer-instructions.blade.php`. Extend the partial rather than forking it; both mails must keep the
  same payment reference wording.

## Tests

All tests are PHPUnit classes. Unit tests extend `Tests\UnitTestCase` and use the existing expectation classes; feature
tests extend `Tests\FeatureTestCase` and use factories and `Mail::fake()` after fixture creation. No Pest tests are
added. Create files with `./Taskfile artisan make:test --phpunit <name>`.

### Unit — data and Mailable

Add `tests/Unit/Domain/Billing/Invoices/InvoicePaymentDeclinedMailDataTest.php`:

- `test_it_stores_all_properties`
- `test_it_stores_the_reauthorisation_flag`

Add `tests/Unit/Domain/Billing/Invoices/Mails/InvoicePaymentDeclinedMailTest.php`:

- `test_it_exposes_the_recipient`
- `test_subject_states_the_payment_failed`
- `test_content_uses_the_declined_notice_template_and_passes_the_data`
- `test_content_passes_the_reauthorisation_flag`
- `test_related_points_to_the_invoice`

The content test follows `InvoiceMailTest`: assert `$content->markdown === 'mail.invoice-payment-declined'` and assert
each `with` key, including `mandateNeedsReauthorisation`.

### Unit — job and listener

Add `tests/Unit/Domain/Billing/Invoices/Jobs/SendInvoicePaymentDeclinedEmailTest.php`, following
`SendInvoiceEmailTest`:

- `test_handle_sends_the_declined_notice`
- `test_handle_does_nothing_when_the_invoice_is_no_longer_declinable`
- `test_handle_does_nothing_when_the_batch_is_cancelled`

Use `InvoiceMailRepositoryExpectation` and `MailSenderExpectation`; the `SepaConfiguration` fixture can be the same
inline value as `SendInvoiceEmailTest`. Add the new expectation method to
`tests/Unit/Domain/Billing/Invoices/InvoiceMailRepositoryExpectation.php`:

```php
public function expectsGetInvoicePaymentDeclinedMailData(
    InvoiceId $invoiceId,
    ?InvoicePaymentDeclinedMailData $return,
): void {
    $this->mock
        ->expects('getInvoicePaymentDeclinedMailData')
        ->with(equalTo($invoiceId))
        ->andReturn($return);
}
```

Add `../../tests/Unit/Domain/Billing/Invoices/Listeners/QueueInvoicePaymentDeclinedEmailTest.php` using
`InvoiceMailRepositoryExpectation` and `JobDispatcherExpectation`:

- `test_handle_dispatches_the_notice_job` — read model returns data; expect
  `new SendInvoicePaymentDeclinedEmail($invoiceId)`.
- `test_handle_does_not_dispatch_when_the_invoice_cannot_be_notified` — read model returns `null`; `expectsNoDispatch()`.

### Feature — repository guards

Extend `tests/Feature/Infrastructure/Billing/Invoices/InvoiceMailRepositoryDbTest.php`:

- `test_get_invoice_payment_declined_mail_data_returns_the_invoice_and_snapshot_recipient` — assert invoice number,
  recipient name/email, date, total and the re-authorisation flag.
- `test_get_invoice_payment_declined_mail_data_returns_null_for_a_non_declined_invoice`
- `test_get_invoice_payment_declined_mail_data_returns_null_for_a_credit_invoice` — set `credit_invoice_id`.
- `test_get_invoice_payment_declined_mail_data_returns_null_for_an_invoice_that_has_a_credit_invoice`
- `test_get_invoice_payment_declined_mail_data_returns_null_when_the_recipient_email_is_blank` — `recipient_email => ''`.
- `test_get_invoice_payment_declined_mail_data_defaults_the_reauthorisation_flag_to_false` — documents the step-4
  hand-off until the mandate model exists.
- `test_get_invoice_payment_declined_mail_data_uses_the_invoice_recipient_snapshot` — create a household member/other
  member with a different email and assert the invoice's snapshot address is used; this covers the shared household
  email case without a household-specific code path.

### Feature — automatic and manual paths

Add `tests/Feature/Scenarios/DeclinedPaymentNoticeScenarioTest.php`. This exercises the event, listener, job, mail and
audit trail together; the queue is synchronous in tests.

```php
public function test_manual_decline_queues_the_declined_notice(): void
{
    $member = Member::factory()->has(PaymentInformation::factory())->createOneQuietly();

    $invoice = Invoice::factory()->forMember($member)->createQuietly([
        'status' => InvoiceStatus::Pending,
        'invoice_number' => 'I-20261001',
        'recipient_email' => 'gezin@example.com',
    ]);

    Mail::fake();

    $this->app->make(InvoiceService::class)->markAsDeclined(
        new InvoiceIdList([InvoiceId::create($invoice->id)]),
        null,
        'Handmatig geweigerd',
    );

    Mail::assertQueued(
        MailMailable::class,
        static fn (MailMailable $mail): bool => $mail->hasTo('gezin@example.com'),
    );
}
```

Methods:

- `test_automatic_reversal_decline_queues_the_declined_notice` — call `markAsDeclined()` with a real
  `BankTransaction::factory()` id as the reversal id, mirroring `linkReversal()`; assert the queued mail. Plan 01's
  `PaymentMatchingScenarioTest` already proves that `linkReversal()` reaches this service, so this test does not
  rebuild the MT940 import.
- `test_manual_decline_queues_the_declined_notice` — the code above.
- `test_manual_decline_sends_no_notice_for_a_credit_invoice` — invoice with `credit_invoice_id` set and `Pending`
  status; assert `Mail::assertNothingQueued()`. (Plan 01 permits the decline transition; this plan suppresses the mail.)
- `test_manual_decline_does_not_send_a_notice_without_a_recipient_email` — member with `email => ''` and
  `forMember()` so the snapshot is blank; assert nothing queued.
- `test_declining_an_already_declined_invoice_does_not_send_a_second_notice` — call `markAsDeclined()` twice; assert
  `Mail::queued(MailMailable::class)` has exactly one item, which is the step-1 transition-only contract.
- `test_manual_decline_records_the_notice_in_the_outgoing_email_log` — do **not** fake mail; the sync queue and array
  transport run `LogOutgoingMail`. Assert `outgoing_emails` contains:

```php
$this->assertDatabaseHas('outgoing_emails', [
    'mailable_class' => InvoicePaymentDeclinedMail::class,
    'recipient_email' => 'gezin@example.com',
    'subject' => 'Betaling van factuur I-20261001 is niet gelukt',
    'member_id' => $member->id,
    'status' => OutgoingEmailStatus::Sent->value,
]);
```

Do not assert `related_model_type`/`related_model_id`; the repository never persists them.
`test_reopening_before_the_job_runs_skips_the_notice` may be covered by the job unit test instead of a second scenario
setup.

### Feature — template copy

Add `tests/Feature/Mail/InvoicePaymentDeclinedMailTemplateTest.php`. Render the view directly with
`view('mail.invoice-payment-declined', $data)->render()`, mirroring the data passed by the Mailable.

- `test_it_states_the_failed_debit_and_reversed_amount` — contains the invoice number, the date, `€ 100,00`, the
  creditor IBAN, the account name and `factuurnummer`.
- `test_it_states_no_decline_reason` — contains "is niet gelukt" and "teruggeboekt", and does **not** contain
  "onvoldoende saldo", "machtiging geweigerd", "terugboekingscode" or a reason placeholder.
- `test_it_renders_the_reauthorisation_hint_when_the_flag_is_set` — with
  `mandateNeedsReauthorisation => true`, contains "nieuwe machtiging" and "ingetrokken of verlopen".
- `test_it_hides_the_reauthorisation_hint_when_the_flag_is_not_set` — with `false`, does not contain "nieuwe
  machtiging".

Add `tests/Feature/Mail/InvoiceMailTemplateTest.php`:

- `test_it_renders_the_shared_transfer_instructions` — render `mail.invoice` with `sepaTransferDate: null` and assert
  the creditor IBAN, account name and `factuurnummer` still appear; this guards the partial extraction.

## Out of scope

- **Retry mechanics.** Automatic or uncapped retries, reopening into a batch and retry counters belong to plan 01.
  This plan only tells the member what happened.
- **Escalation and dunning.** No reminder ladder, surcharges or write-off. The due-date/aging and reminder plans own
  follow-up.
- **Admin copy.** No mail to `FinancialAdministrationRecipient` or `MemberAdministrationRecipient`; the panel and the
  export-readiness report already show the declined invoice.
- **Storing a decline reason.** No `invoices.declined_reason`, no note in the mail. The admin note recorded by plan 01
  is internal decline history and is not sent to the member.
- **Bank return codes.** The MT940 engine discards `/RTRN/` and no column stores a code, so the mail cannot state a
  cause and must not pretend to.
- **Mandate model and admin mandate UI.** Plan 04 owns versioned mandates, revocation, expiry and the create/edit path.
  This plan only consumes the boolean signal.
- **Editable templates and Reply-To.** Denied by the communication decisions; the subject and body stay in code and
  the existing `no-reply` sender is unchanged.
- **Failed-mail handling.** Denied; the notice uses the existing pipeline and does not add retry/error UI.

## Verification

```bash
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Invoices/InvoicePaymentDeclinedMailDataTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Invoices/Mails/InvoicePaymentDeclinedMailTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Invoices/Jobs/SendInvoicePaymentDeclinedEmailTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Invoices/Listeners/SendInvoicePaymentDeclinedNoticeTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Invoices/InvoiceMailRepositoryDbTest.php
./Taskfile artisan test --compact tests/Feature/Scenarios/DeclinedPaymentNoticeScenarioTest.php
./Taskfile artisan test --compact tests/Feature/Mail/InvoicePaymentDeclinedMailTemplateTest.php
./Taskfile artisan test --compact tests/Feature/Mail/InvoiceMailTemplateTest.php
./Taskfile stan
./Taskfile lint
```

`./Taskfile stan` is required because new classes under `app/Domain/**` need matching unit tests
(`extrastan.UnitTestRequired`) and the Mailable adds an Eloquent import. `./Taskfile lint` also runs the pre-commit
checks.
