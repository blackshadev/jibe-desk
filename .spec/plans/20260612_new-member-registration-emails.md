# Plan: Send Emails on New Member Registration

## Overview

When a new member registers via `NewMemberService`, dispatch a `NewMemberRegistration` domain event. Two listeners react to this event: one sends an admin notification email, the other sends a welcome email to the new member. All emails are in Dutch and styled with the organization's branding (logo at `public/images/logo.png`, primary color `#2EA3F2`, secondary color `#ff0000`).

---

## 1. Create the `NewMemberRegistration` Event

### File: `app/Domain/Members/Events/NewMemberRegistration.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Members\Events;

use App\Domain\Members\MemberId;
use App\Domain\Registration\MembershipData;

final readonly class NewMemberRegistration
{
    public function __construct(
        public MemberId $memberId,
        public string $memberName,
        public string $memberEmail,
        public MembershipData $membershipData,
    ) {
    }
}
```

- `memberId` — the `MemberId` value object of the newly created member.
- `memberName` — the formatted display name (via `MemberNameFormatter::displayName()`).
- `memberEmail` — the new member's email address (needed to send them the welcome mail without re-querying).
- `membershipData` — the `MembershipData` from the registration form (windsurfing lessons, RTC, clubhouse access, board storage, federation number).

---

## 2. Update `NewMemberService` to Dispatch the Event

### File: `app/Domain/Members/NewMemberService.php`

Inject `Illuminate\Contracts\Events\Dispatcher` and dispatch the event after member creation:

```php
use App\Domain\Members\Events\NewMemberRegistration;
use App\Domain\Members\MemberNameFormatter;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class NewMemberService
{
    public function __construct(
        private MemberRepository $memberRepository,
        private MembershipRepository $membershipRepository,
        private Dispatcher $eventDispatcher,
    ) {
    }

    public function fromRegistration(FormData $formData): MemberId
    {
        // ... existing validation and DTO construction unchanged ...

        $memberId = $this->memberRepository->newMember($newMember);

        $this->eventDispatcher->dispatch(new NewMemberRegistration(
            memberId: $memberId,
            memberName: MemberNameFormatter::displayName(
                $formData->personalInfo->firstName,
                $formData->personalInfo->infixName,
                $formData->personalInfo->lastName,
            ),
            memberEmail: $formData->personalInfo->email,
            membershipData: $formData->membership,
        ));

        return $memberId;
    }
}
```

No changes to `MembershipRepository`, `Membership` entity, or any infrastructure layer.

---

## 3. Create the Two Mailable Classes

### File: `app/Mail/NewMemberAdminNotification.php` (new)

Admin notification — short, factual, Dutch.

```php
<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Members\MemberId;
use App\Domain\Registration\MembershipData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class NewMemberAdminNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly MemberId $memberId,
        public readonly string $memberName,
        public readonly MembershipData $membershipData,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nieuwe aanmelding: ' . $this->memberName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-member-admin-notification',
            with: [
                'memberName' => $this->memberName,
                'membershipData' => $this->membershipData,
                'editUrl' => route('filament.admin.resources.members.edit', ['record' => $this->memberId->value]),
            ],
        );
    }
}
```

### File: `app/Mail/NewMemberWelcome.php` (new)

Welcome email to the new member — warm, Dutch.

```php
<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class NewMemberWelcome extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $memberName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welkom bij Almere Centraal!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-member-welcome',
            with: [
                'memberName' => $this->memberName,
            ],
        );
    }
}
```

---

## 4. Create the Blade Mail Templates

### Custom Mail Theme: `resources/views/vendor/mail/html/themes/custom.css` (new)

Publish the default theme and customize with brand colors. Set `'theme' => 'custom'` in `config/mail.php`.

```css
/* Based on Laravel default mail theme, with brand overrides */
a { color: #2EA3F2; text-decoration: underline; }
.button-primary { background-color: #2EA3F2; border-color: #2EA3F2; }
.button-primary:hover { background-color: #2590d8; border-color: #2590d8; }
/* Secondary accent for emphasis */
.text-danger, .text-alert { color: #ff0000; }
```

### File: `config/mail.php`

Add the theme and admin address:

```php
'theme' => 'custom',

'admin' => [
    'address' => env('MAIL_ADMIN_ADDRESS', 'ledenadministratie@almerecentraal.nl'),
],
```

### File: `resources/views/mail/new-member-admin-notification.blade.php` (new)

```blade
@component('mail::message')
# Nieuwe aanmelding

Er heeft zich een nieuw lid aangemeld:

**Naam:** {{ $memberName }}

**Aanmeldgegevens:**
- Reguliere surflessen: {{ $membershipData->regularWindsurfingLessons ? 'Ja' : 'Nee' }}
- RTC: {{ $membershipData->rtc ? 'Ja' : 'Nee' }}
- Clubhuis toegang: {{ $membershipData->clubhouseAccess ? 'Ja' : 'Nee' }}
- Board opslag: {{ $membershipData->boardStorage ? 'Ja' : 'Nee' }}
- Watersportbond nummer: {{ $membershipData->watersportFederationNumber ?: 'Niet opgegeven' }}

@component('mail::button', ['url' => $editUrl, 'color' => 'primary'])
Bekijk lid in administratie
@endcomponent

Met vriendelijke groet,<br>
Almere Centraal ledenadministratie
@endcomponent
```

### File: `resources/views/mail/new-member-welcome.blade.php` (new)

```blade
@component('mail::message')
# Welkom bij Almere Centraal!

Beste {{ $memberName }},

Wat leuk dat je lid wilt worden van Watersportvereniging Almere Centraal!

We hebben je aanmelding ontvangen en gaan deze verwerken. We verwachten dit binnen ongeveer twee weken af te ronden. Daarna nemen we contact met je op voor meer informatie.

Mocht je in de tussentijd vragen hebben, neem dan gerust contact met ons op.

Met vriendelijke groet,<br>
Almere Centraal ledenadministratie
@endcomponent
```

---

## 5. Create the Two Listeners

### File: `app/Domain/Members/Listeners/SendAdminNewMemberNotification.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Members\Listeners;

use App\Domain\Members\Events\NewMemberRegistration;use App\Domain\Registration\Mails\NewMemberAdminNotification;use Illuminate\Support\Facades\Mail;

final readonly class SendAdminNewMemberNotification
{
    public function handle(NewMemberRegistration $event): void
    {
        Mail::to(config('mail.admin.address'))
            ->send(new NewMemberAdminNotification(
                memberId: $event->memberId,
                memberName: $event->memberName,
                membershipData: $event->membershipData,
            ));
    }
}
```

### File: `app/Domain/Members/Listeners/SendNewMemberWelcome.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Members\Listeners;

use App\Domain\Members\Events\NewMemberRegistration;use App\Domain\Registration\Mails\NewMemberWelcome;use Illuminate\Support\Facades\Mail;

final readonly class SendNewMemberWelcome
{
    public function handle(NewMemberRegistration $event): void
    {
        Mail::to($event->memberEmail)
            ->send(new NewMemberWelcome(
                memberName: $event->memberName,
            ));
    }
}
```

---

## 6. Add Admin Email Configuration

### File: `.env` and `.env.example`

Add:

```
MAIL_ADMIN_ADDRESS="ledenadministratie@almerecentraal.nl"
```

---

## 7. Register Event-Listener Mapping

### File: `bootstrap/app.php`

Add `->withEvents()` to the application configuration:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withEvents(discover: [
        __DIR__ . '/../app/Domain',
    ])
    // ... rest unchanged
```

This uses Laravel's event auto-discovery to scan listeners in the `app/Domain` directory. Laravel will inspect the `handle()` method's type-hint and automatically register the listener for the `NewMemberRegistration` event.

---

## 8. Register Mailables in Mailbook (dev preview)

### File: `routes/mailbook.php`

Add both mailables for preview:

```php
Mailbook::add(NewMemberAdminNotification::class);
Mailbook::add(NewMemberWelcome::class);
```

---

## 9. Tests

### 9a. Unit test: `NewMemberServiceTest` — update existing

**File:** `tests/Unit/Domain/Members/NewMemberServiceTest.php`

- Add a mock for `Dispatcher` (the event dispatcher contract).
- Update `setUp()` to inject the mock dispatcher as the third constructor argument.
- In `test_it_creates_a_new_member_from_registration_data`: add an expectation that the dispatcher receives a `NewMemberRegistration` event with:
  - `memberId` = `MemberId::create(42)`
  - `memberName` = `'Vries, Jan de'`
  - `memberEmail` = `'jan@example.com'`
  - `membershipData` = the `MembershipData::createDefault()` from the form data
- In `test_it_throws_when_mandate_date_is_missing`: assert the dispatcher's `dispatch` method was never called.

### 9b. Unit test: `NewMemberRegistrationTest` — new

**File:** `tests/Unit/Domain/Members/Events/NewMemberRegistrationTest.php`

Test the event DTO construction and verify all properties are correctly assigned.

### 9c. Feature test: `SendAdminNewMemberNotificationTest` — new

**File:** `tests/Feature/Mail/SendAdminNewMemberNotificationTest.php`

- Use `Mail::fake()`.
- Dispatch a `NewMemberRegistration` event.
- Assert `NewMemberAdminNotification` was sent to the admin address (`config('mail.admin.address')`).
- Assert the mailable has the correct subject.

### 9d. Feature test: `SendNewMemberWelcomeTest` — new

**File:** `tests/Feature/Mail/SendNewMemberWelcomeTest.php`

- Use `Mail::fake()`.
- Dispatch a `NewMemberRegistration` event.
- Assert `NewMemberWelcome` was sent to the member's email.
- Assert the mailable has the correct subject.

### 9e. Feature test: `NewMemberAdminNotificationTest` — new

**File:** `tests/Feature/Mail/NewMemberAdminNotificationTest.php`

- Test the mailable renders correctly.
- Assert it contains the member name, membership data fields, and edit link.

### 9f. Feature test: `NewMemberWelcomeTest` — new

**File:** `tests/Feature/Mail/NewMemberWelcomeTest.php`

- Test the mailable renders correctly.
- Assert it contains the welcome message text and member name.

---

## Summary of All Files to Create or Modify

| Action | File |
|--------|------|
| **Modify** | `app/Domain/Members/NewMemberService.php` — inject `Dispatcher`, dispatch event |
| **Modify** | `config/mail.php` — add `theme` and `admin.address` config |
| **Modify** | `.env` / `.env.example` — add `MAIL_ADMIN_ADDRESS` |
| **Modify** | `bootstrap/app.php` — add `->withEvents()` discovery |
| **Modify** | `routes/mailbook.php` — register new mailables |
| **Create** | `app/Domain/Members/Events/NewMemberRegistration.php` |
| **Create** | `app/Domain/Members/Listeners/SendAdminNewMemberNotification.php` |
| **Create** | `app/Domain/Members/Listeners/SendNewMemberWelcome.php` |
| **Create** | `app/Mail/NewMemberAdminNotification.php` |
| **Create** | `app/Mail/NewMemberWelcome.php` |
| **Create** | `resources/views/mail/new-member-admin-notification.blade.php` |
| **Create** | `resources/views/mail/new-member-welcome.blade.php` |
| **Create** | `resources/views/vendor/mail/html/themes/custom.css` — brand colors |
| **Modify** | `tests/Unit/Domain/Members/NewMemberServiceTest.php` — add event dispatch assertions |
| **Create** | `tests/Unit/Domain/Members/Events/NewMemberRegistrationTest.php` |
| **Create** | `tests/Feature/Mail/SendAdminNewMemberNotificationTest.php` |
| **Create** | `tests/Feature/Mail/SendNewMemberWelcomeTest.php` |
| **Create** | `tests/Feature/Mail/NewMemberAdminNotificationTest.php` |
| **Create** | `tests/Feature/Mail/NewMemberWelcomeTest.php` |

---

## Implementation Order

1. Create `NewMemberRegistration` event
2. Update `NewMemberService` to inject `Dispatcher` and dispatch the event
3. Update `NewMemberServiceTest` with event dispatch assertions
4. Add mail config (`config/mail.php` theme + admin address, `.env`)
5. Create the custom mail CSS theme
6. Create the two Mailable classes
7. Create the two Blade templates
8. Create the two Listeners
9. Register events in `bootstrap/app.php`
10. Register mailables in Mailbook
11. Write feature tests for listeners and mailables
12. Run full test suite to verify nothing is broken
