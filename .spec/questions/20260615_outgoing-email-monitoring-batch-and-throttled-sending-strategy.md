# Outgoing Email Strategy: Monitoring, Batching & Throttled Background Sending

## Summary

Two strategies are recommended, with **Strategy 1 (Event-Driven Mail Tracking + Throttled Queues)** being the primary recommendation as it aligns with the existing DDD architecture and leverages Laravel's native mail events.

| Strategy | Approach | Best For |
|----------|----------|----------|
| **1. Event-Driven Tracking + Throttled Queues** (Recommended) | Listen to `MessageSending`/`MessageSent` events, log to `outgoing_emails` table, queue all mail with rate-limited middleware | Full visibility, native Laravel integration, fits existing event architecture |
| **2. Centralized OutgoingMail Service** | Wrap all mail dispatch in a domain service that creates tracking records and dispatches queued jobs | Simpler mental model, more explicit control, easier to add pre-send logic |

---

## Current State of the Codebase

| Area | Status |
|------|--------|
| Mailables | 2 total (`NewMemberWelcome`, `NewMemberAdminNotification`), sent **synchronously** via `Mail::send()` |
| Queue infrastructure | Database driver fully configured (`jobs`, `job_batches`, `failed_jobs` tables exist) but **completely unused** |
| Invoice emails | **None** — invoices are admin-only, no email delivery to members |
| Mail tracking/logging | **None** |
| Rate limiting (mail) | **None** (only auth rate limiting exists) |
| Event architecture | Clean DDD pattern: events dispatched from services, auto-discovered listeners handle mail |

---

## Strategy 1: Event-Driven Mail Tracking + Throttled Queues (Recommended)

### Architecture Overview

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────────┐
│  Domain Service  │────▶│  Mail::queue()   │────▶│  Queue Worker       │
│  (dispatches)    │     │  (ShouldQueue)   │     │  (rate-limited)     │
└─────────────────┘     └──────────────────┘     └────────┬────────────┘
                                                           │
                                                           ▼
                                                  ┌─────────────────────┐
                                                  │  MessageSent Event  │
                                                  │  → Log to DB        │
                                                  └─────────────────────┘
```

### Component 1: Mail Tracking via `outgoing_emails` Table

Create a migration for an `outgoing_emails` table that logs every outgoing email:

```
outgoing_emails
├── id (uuid)
├── mailable_type (string, indexed) — e.g. 'invoice', 'welcome', 'announcement', 'password_reset'
├── mailable_class (string) — FQCN of the Mailable
├── recipient_email (string, indexed)
├── recipient_name (string, nullable)
├── subject (string)
├── status (enum: queued, sent, failed)
├── message_id (string, nullable) — SMTP/transport message ID (from MessageSent event)
├── batch_id (string, nullable, indexed) — links to job_batches for batch tracking
├── related_model_type (string, nullable) — e.g. 'App\\Models\\Invoice'
├── related_model_id (string, nullable) — polymorphic relation
├── error_message (text, nullable)
├── queued_at (timestamp)
├── sent_at (timestamp, nullable)
├── created_at / updated_at
```

**Why this works**: Laravel dispatches `MessageSending` and `MessageSent` events natively. By listening to these, you get tracking for *all* mail — including Fortify's password reset and email verification — without modifying their internals.

**Listeners to create in `app/Domain/Mail/Listeners/`:**
- `LogOutgoingMail` — listens to `MessageSending`, creates the `outgoing_emails` record with status `queued`
- `MarkMailAsSent` — listens to `MessageSent`, updates the record to `sent` with the `messageId`
- `MarkMailAsFailed` — listens to `MessageFailed` (if available) or uses queue failure hooks

### Component 2: Queue All Mail with `ShouldQueue`

Make all mailables implement `ShouldQueue`. This leverages your already-configured database queue:

- **Invoice emails** → queued on a dedicated `invoices` queue
- **Announcement emails** → queued on an `announcements` queue
- **Registration/welcome emails** → queued on the `default` queue
- **Password reset / email verification** → Fortify's notifications already support queueing; configure via `ShouldQueue` on custom notification classes

For Fortify's built-in notifications (password reset, email verification), override them in `AppServiceProvider::boot()` or on the User model to use queued versions.

### Component 3: Rate Limiting / Throttling

To avoid being flagged as spam when sending batch invoice emails, use **queue middleware** with rate limiting:

**Approach A: Queue-level rate limiting via `RateLimited` middleware**

Laravel 13 supports rate limiting on queues. Create a dedicated `invoices` queue and configure the worker with `--sleep` and `--max-jobs` flags, combined with a custom rate limiter:

```php
// In AppServiceProvider::boot()
RateLimiter::for('invoice-emails', function (object $job) {
    return Limit::perMinute(20); // 20 emails per minute max
});
```

Apply this middleware in the `SendInvoiceEmail` job's `middleware()` method.

**Approach B: Staggered delays with `later()`**

When batch-creating invoice emails, assign each a calculated delay:

```php
foreach ($invoices as $index => $invoice) {
    $delay = now()->addSeconds($index * 3); // 3 seconds between each
    Mail::to($invoice->member->email)
        ->later($delay, new InvoiceMail($invoice));
}
```

**Recommended**: Use Approach A (rate limiter middleware) as the primary mechanism, with Approach B as a supplementary strategy for very large batches.

### Component 4: Batch Invoice Email Dispatch

When an `InvoiceBatch` transitions to `Pending` (via `closeBatch()`), dispatch a job batch:

1. **Event**: `InvoiceBatchClosed` — dispatched from `InvoiceBatchService::closeBatch()`
2. **Listener**: `QueueInvoiceEmails` — creates a `Bus::batch()` of `SendInvoiceEmail` jobs, one per invoice
3. **Job**: `SendInvoiceEmail` — implements `ShouldQueue`, uses the `invoice-emails` rate limiter, sends the mailable

This gives you:
- Progress tracking via `job_batches` table (already exists)
- Ability to pause/cancel a batch
- Visibility into which invoices have been emailed
- Automatic retry on failure

### Component 5: Filament Admin Dashboard for Mail Monitoring

Create a `OutgoingEmailResource` in Filament to view:
- All outgoing emails with filters by status, type, date range
- Batch progress for invoice email batches
- Failed emails with error messages and retry actions
- Stats widget showing sent/queued/failed counts

### How Each Email Type Fits

| Email Type | Trigger | Queue | Tracking | Throttling |
|------------|---------|-------|----------|------------|
| Invoice emails | `InvoiceBatchClosed` event → batch job | `invoices` queue | `outgoing_emails` table + `job_batches` | Rate limiter middleware (20/min) |
| Announcements | Admin action in Filament | `announcements` queue | `outgoing_emails` table | Rate limiter middleware |
| Registration welcome | `NewMemberRegistration` event | `default` queue | `outgoing_emails` table | None (low volume) |
| Password reset | Fortify (custom notification) | `default` queue | `outgoing_emails` table | None (already throttled by Fortify) |
| Email verification | Fortify (custom notification) | `default` queue | `outgoing_emails` table | None (already throttled) |

---

## Strategy 2: Centralized OutgoingMail Service

### Architecture Overview

Instead of relying on Laravel's mail events for tracking, create a domain service that acts as the single entry point for all mail dispatch:

```
┌─────────────────┐     ┌──────────────────────┐     ┌─────────────────────┐
│  Domain Service  │────▶│  OutgoingMailService  │────▶│  Queue Worker       │
│  (calls service) │     │  (tracks + queues)    │     │  (sends + updates)  │
└─────────────────┘     └──────────────────────┘     └─────────────────────┘
```

### How It Works

1. `OutgoingMailService::send(Mailable, recipient, metadata)` — creates an `outgoing_emails` record, then queues a `SendMail` job
2. `SendMail` job — sends the actual mail, updates the tracking record on success/failure
3. All mail sending goes through this service, including Fortify overrides

### Trade-offs vs Strategy 1

| Aspect | Strategy 1 (Event-Driven) | Strategy 2 (Centralized Service) |
|--------|--------------------------|----------------------------------|
| Tracking coverage | Automatic for ALL mail (including third-party packages) | Only for mail sent through the service |
| Complexity | Lower — uses native Laravel events | Higher — must override Fortify notifications |
| Flexibility | Less control over pre-send logic | Full control over dispatch flow |
| Fits existing architecture | Yes — matches the event/listener pattern in `app/Domain/` | Yes — matches the service pattern in `app/Domain/` |
| Fortify integration | Seamless — events fire regardless of sender | Requires overriding `sendPasswordResetNotification` and `toMailUsing` |

---

## Recommendation

**Use Strategy 1** as the primary approach. It:

1. Requires no changes to Fortify's internals — `MessageSent` events fire for all mail regardless of origin
2. Matches your existing event/listener architecture in `app/Domain/`
3. Leverages your already-configured queue infrastructure (`jobs`, `job_batches` tables)
4. Provides the cleanest path to Filament monitoring (query the `outgoing_emails` table)

**Supplement with elements of Strategy 2** for the invoice batch flow specifically — the `InvoiceBatchClosed` event → batch job pattern is essentially a hybrid.

### Implementation Order

1. Create `outgoing_emails` migration and `OutgoingEmail` model
2. Create `MessageSending`/`MessageSent` listeners for tracking
3. Make existing mailables implement `ShouldQueue`
4. Create `InvoiceMail` mailable and `SendInvoiceEmail` job with rate limiter
5. Add `InvoiceBatchClosed` event to `InvoiceBatchService::closeBatch()`
6. Create `QueueInvoiceEmails` listener that dispatches a job batch
7. Build `OutgoingEmailResource` in Filament for monitoring
8. Override Fortify notifications to use queued versions
9. Add announcement email flow when needed

---

## Sources

- [Laravel 13.x Mail — Queueing](https://laravel.com/docs/13.x/mail#queueing-a-mail-message)
- [Laravel 13.x Mail — Events (MessageSending/MessageSent)](https://laravel.com/docs/13.x/mail#events)
- [Laravel 13.x Mail — Delayed Queueing](https://laravel.com/docs/13.x/mail#delayed-message-queueing)
- [Laravel 13.x Mail — Queueing by Default (ShouldQueue)](https://laravel.com/docs/13.x/mail#queueing-by-default)
- [Laravel 13.x Queues — Job Batching](https://laravel.com/docs/13.x/queues#job-batching)
- [Laravel 13.x Queues — Rate Limiting](https://laravel.com/docs/13.x/queues#rate-limiting)
- [Laravel 13.x Verification — Customization](https://laravel.com/docs/13.x/verification#verification-email-customization)
- [Laravel 13.x Passwords — Reset Email Customization](https://laravel.com/docs/13.x/passwords#reset-email-customization)
