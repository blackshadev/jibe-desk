# Communication & reminders gap inventory

**Date**: 2026-10-21
**Question**: Which communication and reminder features does WSV Admin lack? Audit the Mail context and every business event that could warrant a mail, then propose candidate missing features for the judge ticket to confirm or deny.

---

## Summary

The mail *pipeline* is in good shape: every mail flows through `MailSender` → queued `MailMailable`, is recorded in the `outgoing_emails` audit trail, batch invoice mail is dispatched as a job batch, and the five existing Mailables cover registration, acceptance, invoicing, and batch-creation events. What is missing is nearly everything *around* the pipeline: follow-up mail (reminders, decline notices, declaration status mails), bulk communication, reply handling, and failure/bounce hygiene. Notably, the mail audit trail's `failed` state and `error_message`/`batch_id` columns are displayed in the admin panel but **never written by any code** — a failed SMTP send silently leaves the record stuck at `queued` and the dashboard widget permanently reads zero.

Proposed candidates:

| # | Candidate | Size | One-line gist |
|---|---|---|---|
| 1 | Payment reminder mails (herinnering/aanmaning) for manual payers | medium | Manual transfer payers get one invoice mail and then silence; nothing in the system ever asks them to pay. |
| 2 | Declined-SEPA-payment notice to the member | small | A bounced direct debit marks the invoice `declined` and the member is never told. |
| 3 | Declaration (purchase order) status mails to the member | small–medium | Declarations have a full approval/payment workflow but zero events and zero mails; members only learn outcomes by asking. |
| 4 | Bulk announcements / newsletter to member subsets | large | No bulk mail capability of any kind exists; club announcements go from a personal mailbox with no audit. |
| 5 | Failed-mail handling, retry, and audit-trail completion | small–medium | `Failed` status, `error_message`, `batch_id` are dead UI; failed sends are invisible and unretryable; Fortify mails aren't audited at all. |
| 6 | Reply-to handling | small | From-address is `no-reply@…`, no Reply-To is ever set — member replies to mails that literally invite contact go nowhere. |
| 7 | Editable mail templates | medium (low urgency) | All five templates are hardcoded Blade; only Mailbook (dev route) can preview them. |

Refuted by the code (see *Checked and already covered*): a separate member-acceptance mail, an admin notification for member-filed declarations (members cannot file declarations), invoice mails for manual payers (already sent, with transfer instructions), and batch/throttled sending as a *missing* feature (batch exists; throttling was built, then deliberately disabled — recorded here as a hygiene fact, not a proposal).

---

## 1. Current state: the Mail context

### 1.1 Pipeline and audit trail

- **Send port**: `App\Domain\Mail\MailSender` (interface) → `App\Infrastructure\Mail\LaravelMailSender`, which wraps the domain `BaseMail` in `MailMailable` (an `Illuminate\Mail\Mailable` implementing `ShouldQueue`, `app/Infrastructure/Mail/MailMailable.php`) and calls `Mail::to()->queue()`. All mails are queued; nothing sends synchronously.
- **`BaseMail` contract** (`app/Domain/Mail/BaseMail.php`): every mail declares `subject()`, `to()` (a `Recipient` name+email), `content()` (Blade markdown view + data), and optionally `related()` (a `Related` polymorphic pointer to the triggering model). Subclasses live in the owning context (`Registration`, `Members`, `Billing`), not in Mail — Mail is a support context others send through.
- **Audit trail**: `App\Domain\Mail\Listeners\LogOutgoingMail` listens on `Illuminate\Mail\Events\MessageSending` (auto-discovered: `bootstrap/app.php` discovers listeners under `app/Domain`), generates a UUID `TrackingId`, injects it as an `X-Tracking-ID` header, resolves the member via `MemberRepository::getByEmail()`, and queues an `OutgoingEmail` row with status `queued`. `MarkMailAsSent` listens on `MessageSent` and flips the row to `sent` with `message_id` + `sent_at`. Headers `X-Mailable-Class`, `X-Related-Model`, `X-Related-Id` come from `MailMailable::headers()`.
- **Repository**: `OutgoingEmailRepository` has exactly two methods: `queue()` and `markAsSent()` (`app/Domain/Mail/OutgoingEmailRepository.php`). There is **no method to mark a mail failed** — see candidate 5.
- **Admin surface**: read-only `OutgoingEmailResource` (list-only, Technical navigation group), `OutgoingEmailsRelationManager` on the member resource, and `FailedOutgoingEmailsWidget` on the dashboard (`app/Filament/Admin/Widgets/Dashboard/FailedOutgoingEmailsWidget.php`, visible only to `RoleName::TechnicalAdministration`).
- **Internal recipients**: `MemberAdministrationRecipient` (config `mail.admin` → `ledenadministratie@almerecentraal.nl`) and `FinancialAdministrationRecipient` (config `mail.invoicing` → `penningmeester@almerecentraal.nl`), both bound in `AppServiceProvider::register()`. These are the only two admin inboxes the system knows.

### 1.2 The five Mailables that exist today

| Mailable | Recipient | Trigger | Sender |
|---|---|---|---|
| `RegistrationReceived` (`app/Domain/Registration/Mails/`) | member | public registration wizard completes (`NewMemberRegistration` event from `NewMemberService::fromRegistration`) | `SendRegistrationReceived` listener |
| `NewMemberAdminNotification` (same folder) | member-admin inbox | same event, **skipped when `RegistrationSource::AdminPanel`** (admin-filed members) | `SendAdminNewMemberNotification` |
| `MemberWelcome` (`app/Domain/Members/Mails/`) | member | acceptance (`AcceptMemberService::accept` → `MemberAccountProvisioned`) or admin `CreateUserAccountAction`; contains set-password + login URLs | `SendMemberWelcome` |
| `InvoiceMail` (`app/Domain/Billing/Invoices/Mails/`) | member | (a) `SepaBatchClosed` → `QueueInvoiceEmails` → one `SendInvoiceEmail` job per invoice in a `JobBatch`; (b) `InvoiceServiceImpl::markAsPending` (admin action / reversal unlink), suppressed if already sent; (c) `ResendInvoiceEmailAction` (policy-guarded, `isResend: true`) | `SendInvoiceEmail` job |
| `SepaBatchCreatedMail` (`app/Domain/Billing/Payment/Mails/`) | financial-admin inbox | SEPA batch generation finishes — chained after the generation `JobBatch` in `SepaBatchGeneratorImpl::generate()` | `SendSepaBatchCreatedEmail` job |

Templates: five Dutch Blade files in `resources/views/mail/` (`registration-received`, `new-member-admin-notification`, `member-welcome`, `invoice`, `sepa-batch-created`), previewable in Mailbook (`routes/mailbook.php` — dev-only route, all five registered). Subjects are hardcoded in the Mailable classes; no template is editable from the panel.

`InvoiceMail` renders three variants from `InvoiceMailData` (`sepaTransferDate` nullable): direct debit ("geïncasseerd op …"), manual transfer ("dient u over te maken naar …" with creditor IBAN and invoice number), and credit (negative total → "u ontvangt het terug te betalen bedrag"). A manual payer is a member whose `paymentInformation.mandate_accepted_date` is null — the branch is chosen in `InvoiceMailRepositoryDb::getInvoiceMailData()` (line 45).

Framework mails (Fortify `resetPasswords`, `emailVerification`, `updatePasswords`, 2FA — `config/fortify.php`) bypass all of this: they carry no `X-Mailable-Class` header, so `LogOutgoingMail` skips them and they never appear in the audit trail.

### 1.3 Batch and throttled sending — correcting the record

Batch sending exists and is used: `JobBatch`/`JobChain`/`JobDispatcher` (domain seam, `app/Domain/Jobs/`) → `LaravelJobDispatcher` → `Bus::batch`. Invoice mails per closed batch run as one named batch (`QueueInvoiceEmails`), each job `tries = 3` (`BaseJob`).

Throttling, however, is **currently disabled**: the `invoice-emails` rate limiter (20/min) was implemented 2026-06-17 (commit `67dd7d3`) and commented out on 2026-06-24 in commit `a26af38` — `AppServiceProvider::boot()` line 58 is `//        RateLimiter::for('invoice-emails', …)` and no job carries `RateLimited` middleware. The plan title "batch & throttled sending" (`.spec/plans/20260616_…`) describes what was built *and then partially turned off*. This is a hygiene fact for the record, not a proposed feature — see candidate 5 for the related failure-handling gap.

### 1.4 Mail configuration and reply behaviour

- `config/mail.php`: `from` = `MAIL_FROM_ADDRESS` (`.env.example`: `no-reply@almerecentraal.nl`); no `reply_to` is configured anywhere and `MailMailable::envelope()` sets only the subject — **no mail in the system carries a Reply-To header**.
- `.env.example` lacks `MAIL_INVOICING_NAME`/`MAIL_INVOICING_ADDRESS`, so the financial-admin inbox falls back to config defaults; also `mail.admin.name` reads `MAIL_INVOICING_NAME` (config/mail.php line 123), i.e. the two admin inboxes share one name variable — a small config quirk worth fixing whenever mail config is touched again.
- No bounce/DSN processing exists at all: there is no inbound mail path (the only public entry point is the registration wizard, `routes/web.php`), so bounces can only be noticed by a human at the SMTP provider.

### 1.5 Event → mail coverage matrix (business events that do *not* mail)

| Business event | Code location | Member mail? | Admin mail? |
|---|---|---|---|
| Public registration received | `NewMemberService::fromRegistration` → `NewMemberRegistration` | yes | yes |
| Member accepted | `AcceptMemberService::accept` → `MemberAccepted` | (none directly — but `MemberAccountProvisioned` immediately follows and sends `MemberWelcome`) | no |
| Account provisioned | `AcceptMemberService` / `CreateUserAccountAction` → `MemberAccountProvisioned` | yes | no |
| SEPA batch generated | `SepaBatchGeneratorImpl` (job chain) | — | yes (`SepaBatchCreatedMail`) |
| SEPA batch closed → invoice mails | `SepaBatchServiceImpl::closeBatch` → `SepaBatchClosed` → `QueueInvoiceEmails` | yes (per invoice) | no (admin performed the close themselves) |
| Invoice re-opened to pending | `InvoiceServiceImpl::markAsPending` | re-send (suppressed if already sent once) | no |
| **Invoice marked declined** — manually | `InvoiceServiceImpl::markAsDeclined`, via `InvoiceStateActions`/`InvoicesTable` bulk actions | **no** | no |
| **Invoice declined via SEPA storno** | `BankTransactionServiceImpl::linkReversal()` → `invoiceService->markAsDeclined()` | **no** | no |
| Invoice marked paid | `InvoiceServiceImpl::markAsPaid` | no | no |
| **Declaration approval accepted/declined, payment pending/paid/declined** | `PurchaseOrderServiceImpl` (all six transitions) | **no** | **no** — the PurchaseOrders context dispatches **no events at all** |
| Unresolvable bank transaction | `BankTransactionServiceImpl::resolveMatching` → `markAsUnresolvable` | — | no |
| Membership stopped | `MemberObserver::updated` → `ApplyStoppedMembership` (billing only) | no | no |

---

## 2. Candidate 1 — Payment reminder mails (herinnering/aanmaning) for manual payers

**What exists today.** Manual payers (no SEPA mandate: `payment_information.mandate_accepted_date` is null) receive exactly one mail per invoice — `InvoiceMail` with transfer instructions (`resources/views/mail/invoice.blade.php` lines 28–34). The invoice carries `sent_at` (`InvoiceMailRepositoryDb::markInvoiceAsSent`), and invoice status is tracked (`InvoiceStatus`: Open/Pending/Paid/Declined). Beyond that: nothing. A repo-wide search for `herinnering|aanmaning|reminder` finds zero application code. The only overdue-awareness is a *plan* — `OverdueInvoicesWidget` (aging buckets 1–29/30–59/60+ days) in `.spec/plans/20261020_financial-dashboard-widgets.md`, labels already present in `lang/nl/labels.php` (lines 411–413) but no widget implemented.

**What is missing.** Any mail that follows up an unpaid transfer invoice: a reminder after a payment term, optionally an escalation tier (herinnering → aanmaning with statutory surcharges, art. 6:96 BW cost compensation for eenmalige aanmaning), and a guard against double-sending. Members can see their open invoices in the panel (`MyOpenInvoicesWidget`) but most never log in.

**Admin/finance pain.** The treasurer must track unpaid transfer invoices by hand (or via the future aging widget) and chase members from a personal mailbox — inconsistent, unauditable, and easy to forget. Unpaid manual invoices silently age; there is no dunning state anywhere.

**Seam (binding for this map).** The *mail flow* is this ticket's: reminder Mailable + template + trigger + audit. The *payment mechanics* — dunning/escalation state on invoices, payment terms, marking paid manually — belong to the SEPA & payment robustness inventory (ticket 01, "Escalation for unpaid invoices (herinnering → aanmaning → handover)"). A scheduler seam already exists: `routes/console.php` schedules `GenerateSepaBatchCommand` monthly and `MatchBankTransactionsCommand` hourly, so a scheduled reminder command has a natural home.

**Size.** Medium (mail-side): template + recipient selection (pending manual-payer invoices older than term, not yet reminded) + command/action + OutgoingEmail audit + tests. Assumes ticket 01 decides the dunning-state mechanics.

---

## 3. Candidate 2 — Declined-SEPA-payment notice to the member

**What exists today.** When a direct debit bounces, `BankTransactionServiceImpl::linkReversal()` matches the reversal pair, marks the attached invoices `declined` (`invoiceService->markAsDeclined()`) and purchase orders payment-declined, then resolves both transactions. The admin sees the declined invoice in the panel (status badge, `InvoiceStateActions` allows re-pending it). The member sees nothing — no Mailable references a decline, and `InvoiceServiceImpl::markAsDeclined()` (line 34–37) only updates the repository.

**What is missing.** A mail to the member: "de automatische incasso van factuur X is mislukt / wordt gestorneerd; betaal opnieuw via overboeking of herautoriseer uw machtiging." Both trigger paths need it: the automatic storno path (`linkReversal`) and the manual admin decline (`InvoiceStateActions::markAsDeclined`). The invoice mail's transfer-instruction block is reusable content.

**Admin/finance pain.** Bounced debbits are the treasurer's most awkward conversations today: the member often doesn't know until a human chases them; the association waits for money; re-collection needs admin action. A notice mail turns a silent status change into a member-facing call to action, and the declined invoice already sits in the audit-friendly pipeline.

**Seam.** What happens *after* the notice (retry in next batch, manual follow-up, escalation) is ticket 01's mechanics; this candidate is only the mail.

**Size.** Small: one Mailable + template + a decline event or a hook in `InvoiceServiceImpl::markAsDeclined` + listener + test.

---

## 4. Candidate 3 — Declaration (purchase order) status mails to the member

**What exists today.** Declarations are member-assigned purchase orders (`purchase_orders.member_id`, built September 2026 via `.spec/plans/20260923_purchase-order-member-assignment-decline-and-notes.md` and `20261018_purchase-order-accept-decline-payment-status.md`), labelled "declaraties" in Dutch (`lang/nl/labels.php` line 176). The workflow is complete: `PurchaseOrderApprovalStatus` (Pending/Accepted/Declined) plus `PurchaseOrderStatus` (Open/Pending/Paid/Declined/Cancelled), driven through `PurchaseOrderServiceImpl` (`acceptApproval`, `declineApproval` — with a stored `declined_reason` — `markAsPaymentPending`, `markAsPaid`, `markAsPaymentDeclined`). Members can *view* their own purchase order in the panel (`MemberPortalScopingTest`) but cannot manage it. **The context dispatches no domain events and has no `Mails/` folder** — zero mails exist.

**What is missing.** Status mails to the member whose declaration it is: approved (payment pending), paid (reimbursement on its way), and especially declined — with the already-stored `declined_reason` as the mail body. An admin notification "a member filed a declaration" is *not* missing, because members cannot file declarations through the system (see *Checked and already covered*).

**Admin/finance pain.** "Wanneer krijg ik mijn declaratie?" is a classic treasurer inbox; declined declarations are currently silent — the member discovers the rejection only by asking or by noticing nothing arrived. Three small mails eliminate the whole category of status questions and give declines a paper trail.

**Size.** Small–medium: introduce a domain event per transition (the context has none — `PurchaseOrderServiceImpl` needs an `Illuminate\Contracts\Events\Dispatcher` like its siblings), plus 2–3 Mailables + templates + listeners + tests.

---

## 5. Candidate 4 — Bulk announcements / newsletter to member subsets

**What exists today.** Nothing. A repo-wide search for `nieuwsbrief|announcement|newsletter` finds no application code (only a "future idea" line in `.spec/questions/20260615_…strategy.md`). All five Mailables are one-recipient transactional mails. The only bulk-ish machinery is the `JobBatch` dispatch seam and the `OutgoingEmail` audit trail — both reusable as-is.

**What is missing.** An admin action: compose an announcement, choose recipients (all members / active only / by membership / volunteers / activity participants — all queryable: `Member::scopeActive` on `accepted_at`/`stopped_at`, `membership_id`, `is_volunteer`, activities), deduplicate household emails (members within a household may share an email — `getByEmail` already silently picks the first), send as a throttled batch, everything logged as `OutgoingEmail`. Note Dutch anti-spam law (art. 11.7 Telecommunicatiewet): mail to own members about the association's own non-commercial matters is exempt, but a consent flag is cheap insurance for anything commercial (sponsors, offers).

**Admin/finance pain.** Club communication today leaves from a board member's private mailbox — no filter (only active members? only windsurfers?), no audit, no consistency with the member database. The member list to mail is maintained by hand, which is exactly the stale-address problem the `outgoing_emails` audit was built to avoid.

**Boundary (map-binding).** Member-facing *emails* are in scope; a member self-service web surface (e.g. members choosing their own subscriptions) is out of scope for this map. Notification preferences (candidate adjacent) therefore need an admin-side representation — see below.

**Size.** Large: recipient-selection UI, content composition (plain/markdown), consent column + migration, batch send + audit, tests. The send mechanics themselves are small; the selection UX and consent handling are the bulk.

---

## 6. Candidate 5 — Failed-mail handling, retry, and audit-trail completion

**What exists today.** The UI *pretends* failure handling exists: `OutgoingEmailStatus::Failed` (enum), the `failed` filter option in `OutgoingEmailsTable`, `error_message` and `batch_id` columns (migration `2026_06_17_070508_create_outgoing_emails_table.php`), and `FailedOutgoingEmailsWidget` on the dashboard. But `OutgoingEmailRepository` has no `markAsFailed`, `OutgoingEmailRepositoryDb` writes only `queued`/`sent`, no listener catches send failures, and the factory never produces a `Failed` row. A failed SMTP send (after the 3 job tries) lands the framework's queued-mailable job in `failed_jobs` — invisible to the panel — while the `outgoing_emails` row stays `queued` forever. **The dashboard's "mislukte e-mails" stat is structurally always 0.** Two more gaps in the same vein: (a) Fortify's password-reset/verification mails aren't audited at all (no `X-Mailable-Class` header → `LogOutgoingMail` skips them); (b) `batch_id` is never populated, so the invoice-mail job batch is not correlated with its OutgoingEmail rows.

**What is missing.** Mark rows failed with an error message (a queue-failure hook or a scheduled sweep promoting stale `queued` rows — a sweep is the robust option given sends run inside Laravel's `SendQueuedMailableJob`, not our own jobs), surface failures to TechnicalAdministration (the widget already exists), a retry path (per-type resend already exists for invoices via `ResendInvoiceEmailAction`; a generic retry would need the mailable to be reconstructible from the related model), populate `batch_id` from the job batch, and decide whether Fortify mails should be auditable (they can be: the listener pattern already intercepts all sends).

**Admin/finance pain.** Today a misconfigured SMTP host or a rejected recipient means members silently never receive invoices or welcome mails while the audit trail claims everything is queued. This is the one candidate that is about *trust in the existing pipeline* rather than new mail.

**Size.** Small (sweep + error message + surface the existing widget realistically) to medium (true failure correlation + generic retry + Fortify audit coverage).

---

## 7. Candidate 6 — Reply-to handling

**What exists today.** Every mail is sent from `no-reply@almerecentraal.nl` (`.env.example` line 49), `MailMailable::envelope()` sets only the subject, and no `replyTo` appears anywhere in `app/`. Yet the templates invite contact: the invoice mail says "Mocht u vragen hebben… neem dan gerust contact met ons op" (`resources/views/mail/invoice.blade.php`), and the welcome mail likewise.

**What is missing.** A Reply-To header per context: invoice/SEPA mails → financial administration (`penningmeester@almerecentraal.nl`, already in `config/mail.php`), registration/member mails → member administration (`ledenadministratie@almerecentraal.nl`). Both addresses are already configured and bound as `Recipient` objects — this is wiring that exists, unused.

**Admin/finance pain.** Member replies (about a wrong invoice amount, a failed debit, "which bank account?") currently vanish into a no-reply black hole; members who don't notice the address get no answer and escalate awkwardly. Cheap fix, real UX gain.

**Size.** Small: a `replyTo()` on `BaseMail` (defaulting per context or per Mailable), rendered into `MailMailable`'s envelope, plus tests.

---

## 8. Candidate 7 — Editable mail templates

**What exists today.** Five hardcoded Blade templates with subjects in the Mailable classes; Mailbook (`routes/mailbook.php`) previews them on a dev-only route; the mail theme is a fixed Blade layout (`config/mail.php` `'theme' => 'custom'`). No template content is stored in the database, and no admin can alter so much as a subject line without a deploy.

**What is missing.** Admin-editable subject/body per mail type, with placeholder tokens and a preview (Mailbook could serve as the preview engine).

**Honest assessment.** This is a genuine gap but the weakest value case of the seven: a club of this size changes mail texts rarely, developers are available, and Mailbook already covers safe previewing. Recorded so the judge ticket can rank it (likely below the others), not to advocate for it.

**Size.** Medium: template entity + token substitution per mail type + admin resource + preview wiring + tests.

---

## Notification preferences (hypothesis from the charting session)

No preference model exists in any form: `members` has a single non-nullable `email` column (migration `2026_05_14_134742`), no opt-out flags, and Filament database notifications are not enabled. As a *standalone* candidate it is weak — transactional mails (invoice, decline notice, welcome) must be sent regardless. Preferences only become meaningful together with candidate 4 (bulk announcements: a consent flag and per-category opt-out). Recommendation: fold "notification preferences" into the bulk-communication candidate rather than proposing it separately. Storing the preference admin-side (a boolean column read at send time) keeps it inside this map's boundary; any member self-service toggle UI is out of scope per the map.

---

## Checked and already covered (hypotheses the code refutes)

1. **"Members who pay manually don't get an invoice mail"** — refuted. Every invoice in a closed batch is mailed, including manual payers; `InvoiceMailRepositoryDb::getInvoiceMailData()` branches on `mandate_accepted_date` and the template renders explicit transfer instructions with creditor IBAN and payment reference. Only the *reminder* is missing (candidate 1).
2. **"Acceptance workflow needs a mail"** — refuted. `AcceptMemberService::accept()` marks accepted, then immediately provisions the account and dispatches `MemberAccountProvisioned`, which `SendMemberWelcome` turns into the acceptance notice (with set-password link). The admin toast even confirms it: `notifications.member_accepted` = "Lid geaccepteerd; account aangemaakt en welkomstmail verstuurd." A separate "you're accepted" mail would duplicate that.
3. **"Admin notification when a member files a declaration"** — refuted as stated. Members cannot file declarations in the system: declarations are created by admins (`CreatePurchaseOrder`, or derived from a bank transaction via `CreatePurchaseOrderFromTransactionAction`), and the member surface is view-only (`MemberPortalScopingTest`: `Gate::allows('view', $own)` true, `update`/`delete`/`ListPurchaseOrders` denied). There is no member-filing event to notify about. The real gap is the reverse direction — see candidate 3. (If a member-submission surface were ever built — out of scope for this map — an admin-notification mail would become relevant at that moment.)
4. **"New registration needs an admin notification"** — already exists (`NewMemberAdminNotification`), including the sensible guard that suppresses it when the admin created the member in the panel (`SendAdminNewMemberNotification`, `RegistrationSource::AdminPanel`).
5. **"SEPA batch events need admin mail"** — partly covered. The admin gets `SepaBatchCreatedMail` when generation finishes (job-chained in `SepaBatchGeneratorImpl`). Close/complete have no mail, but those are actions the admin performs themselves in the panel — low pain, correctly skipped.
6. **"Resend invoice mail is missing"** — already exists: `ResendInvoiceEmailAction` with `InvoicePolicy::resendEmail` and an `isResend` bypass of the already-sent guard.
7. **"Batch + throttled sending is missing"** — refuted (mostly). Batch sending exists and is in active use (`JobBatch` → `Bus::batch` for invoice mails). Throttling was built then intentionally disabled (see §1.3); the throttle is a deliberate removal, not a missing feature — flagging it for the record only.

## Considered but not proposed (weak value case)

- **"Invoice paid" confirmation mail to the member** — a real non-existent event, but low pain: SEPA payers were told the collection date upfront, and manual payers are better served by candidate 1's reminder sequence than by a per-payment receipt. Listed here so the judge ticket can consciously deny it.

## Facts adjacent tickets may want

- For **SEPA & payment robustness (ticket 01)**: the reminder *mail* flow is candidate 1 here; the dunning/escalation *mechanics* belong there. Manual-payer detection is `mandate_accepted_date === null` (`InvoiceMailRepositoryDb`); invoices have `sent_at` but no due-date; the scheduler lives in `routes/console.php`.
- For **financial reporting (ticket 02)**: `batch_id` on `outgoing_emails` is never populated, so mail-batch reconciliation is not possible today (part of candidate 5).
- The `OutgoingEmail` member attribution uses `MemberRepository::getByEmail()` — first match only; household members sharing one email are attributed to whichever member is found first. Harmless today (display-only), but bulk mail (candidate 4) must deduplicate household emails itself.

## Sources

Codebase (primary):

- `app/Domain/Mail/**` — `BaseMail`, `MailSender`, `OutgoingEmail`, `OutgoingEmailRepository`, `OutgoingEmailStatus`, `Listeners/LogOutgoingMail`, `Listeners/MarkMailAsSent`, `Recipient`, `Related`, `FinancialAdministrationRecipient`, `MemberAdministrationRecipient`
- `app/Infrastructure/Mail/**` — `LaravelMailSender`, `MailMailable`, `OutgoingEmailRepositoryDb`, `TrackingIdGeneratorImpl`
- Mailables and senders: `app/Domain/Registration/Mails/{RegistrationReceived,NewMemberAdminNotification}.php`, `app/Domain/Members/Mails/MemberWelcome.php`, `app/Domain/Billing/Invoices/Mails/InvoiceMail.php`, `app/Domain/Billing/Payment/Mails/SepaBatchCreatedMail.php`; listeners in `app/Domain/Members/Listeners/` and `app/Domain/Billing/Invoices/Listeners/QueueInvoiceEmails.php`; jobs `SendInvoiceEmail`, `SendSepaBatchCreatedEmail`
- Workflows: `app/Domain/Members/{NewMemberService,AcceptMemberService}.php`, `app/Domain/Billing/Payment/{SepaBatchServiceImpl,SepaBatchGeneratorImpl}.php`, `app/Domain/Billing/Invoices/InvoiceServiceImpl.php`, `app/Domain/Billing/PurchaseOrders/PurchaseOrderServiceImpl.php`, `app/Domain/Bookkeeping/Banking/BankTransactions/BankTransactionServiceImpl.php`
- Admin surface: `app/Filament/Admin/Resources/OutgoingEmails/**`, `app/Filament/Admin/Widgets/Dashboard/{FailedOutgoingEmailsWidget,MyOpenInvoicesWidget,OpenInvoicesWidget}.php`, `app/Filament/Admin/Resources/Invoices/Actions/{InvoiceStateActions,ResendInvoiceEmailAction}.php`, `app/Filament/Admin/Resources/PurchaseOrders/**`, `app/Filament/Admin/Resources/SepaBatches/Pages/EditSepaBatch.php`
- Config/infra: `config/mail.php`, `config/fortify.php`, `.env.example`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `routes/console.php`, `routes/web.php`, `routes/mailbook.php`, `resources/views/mail/*.blade.php`, `database/migrations/2026_06_17_070508_create_outgoing_emails_table.php`, `database/migrations/2026_05_14_134742_create_members_table.php`
- Specs: `.spec/plans/20260612_new-member-registration-emails.md`, `.spec/plans/20260616_outgoing-email-monitoring-batch-throttled-sending.md`, `.spec/plans/20260804_declined-credited-sepa-payments.md`, `.spec/plans/20261008_member-acceptance-workflow.md`, `.spec/plans/20260923_purchase-order-member-assignment-decline-and-notes.md`, `.spec/plans/20261018_purchase-order-accept-decline-payment-status.md`, `.spec/plans/20261020_financial-dashboard-widgets.md`, `.spec/questions/20260615_outgoing-email-monitoring-batch-and-throttled-sending-strategy.md`, `.spec/questions/20260923_member-declarations-implementation.md`, `.agents/docs/mail/CONTEXT.md`, `.agents/docs/CONTEXT-MAP.md`
- Git history: commit `67dd7d3` (throttle added), commit `a26af38` (throttle commented out)
