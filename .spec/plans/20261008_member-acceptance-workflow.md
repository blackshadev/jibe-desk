# Implementation Plan: Member Acceptance Workflow

**Date:** Thu Oct 08 2026

## Overview

Newly registered members get a separate **pending** status so the member administration can review them before they become full members. Pending members get no login account (and therefore no password email). Registration still sends the applicant an acknowledgment email ("we received your registration, we will contact you once processed"). The administration accepts members — individually on `ViewMember` and in bulk on `ListMembers` — through a domain service that marks them accepted, creates their account, and sends a new welcome email with password-reset and login instructions. Pending members cannot be linked to invoices, activities, purchase orders, member objects, or storage space rentals, and are excluded from billing until accepted.

## Summary of current situation

- Public registration (`RegistrationController` → `NewMemberService::fromRegistration`) creates a `Member`, **immediately provisions a `User`** via `MemberUserRepository::provision`, then dispatches `NewMemberRegistration`.
- `NewMemberRegistration` is auto-discovered (listeners discovered from `app/Domain` via `bootstrap/app.php` `->withEvents(discover: [...])`) and triggers two listeners: `SendNewMemberWelcome` (member "set your password" email) and `SendAdminNewMemberNotification` (admin notification).
- `Member` has `user_id` (nullable) and `stopped_at` (nullable). "Active"/"inactive" is derived from `stopped_at` only; there is no pending state. `Member::scope active()` is `whereNull('stopped_at')`.
- `ListMembers` has `active`/`inactive` tabs (no badge counters). `ViewMember` header has only `EditAction`; `EditMember` has `CreateUserAccountAction` + `StopAction`.
- `MemberObserver::created` applies membership/volunteer/household billing for **every** new member, so billable items (→ invoices) are created immediately.
- Entities link to members via relation managers on the member page (Invoices, Activities, PurchaseOrders, MemberObjects, StorageSpaceRentals) and via `member_id` selectors in other resources (Invoice, PurchaseOrder, StorageSpaceRental, transaction→invoice, household, activity attach).

## Summary of planned changes

1. Add a nullable `accepted_at` timestamp to `members` and derive three states: pending (`accepted_at IS NULL`), active (`accepted_at NOT NULL AND stopped_at IS NULL`), inactive (`stopped_at NOT NULL`).
2. Registration no longer provisions an account. It still notifies the admin and now sends the applicant an acknowledgment email ("registration received"). The `SendNewMemberWelcome` listener and `NewMemberWelcome` mail are replaced by a `SendRegistrationReceived` listener and a `RegistrationReceived` mail.
3. Introduce an `AcceptMemberService` domain service that marks a member accepted, provisions the account, and dispatches `MemberAccepted` (billing) and `MemberAccountProvisioned` (welcome) events; a `SendMemberWelcome` listener sends the `MemberWelcome` mail on `MemberAccountProvisioned`.
4. Defer billing to acceptance: `MemberObserver` skips billing for pending members on create; a dedicated `ApplyMemberAcceptedBilling` listener applies it when `MemberAccepted` is dispatched.
5. Filament UI: "Nieuw" tab with badge counter on `ListMembers`, an Accept action on `ViewMember`, an Accept bulk action on `MembersTable`, and hide `CreateUserAccountAction` for pending members.
6. Prevent linking entities: make the five member relation managers read-only for pending members and exclude pending members from every `member_id` selector.
7. Add the email template, translations, and tests.

---

## Change 1 — Data model: `accepted_at`

### Change 1.1 — Migration

File: `database/migrations/2026_10_08_000000_add_accepted_at_to_members_table.php` (new)

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', static function (Blueprint $table): void {
            $table->timestamp('accepted_at')->nullable()->after('stopped_at');
        });

        DB::table('members')
            ->whereNull('accepted_at')
            ->update(['accepted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('members', static function (Blueprint $table): void {
            $table->dropColumn('accepted_at');
        });
    }
};
```

### Change 1.2 — Eloquent model scopes and helper

File: `app/Models/Member.php`

Add the cast:

```php
protected function casts(): array
{
    return [
        'birthdate' => 'date',
        'is_volunteer' => 'boolean',
        'gender' => Gender::class,
        'registration_data' => 'array',
        'stopped_at' => 'timestamp',
        'accepted_at' => 'timestamp',
    ];
}
```

Replace the `active` scope and add `pending`/`accepted` scopes plus an `isPending()` helper:

```php
#[Scope]
protected function active(Builder $query): Builder
{
    return $query->whereNull('stopped_at')->whereNotNull('accepted_at');
}

#[Scope]
protected function pending(Builder $query): Builder
{
    return $query->whereNull('accepted_at');
}

#[Scope]
protected function accepted(Builder $query): Builder
{
    return $query->whereNotNull('accepted_at');
}

public function isPending(): bool
{
    return $this->accepted_at === null;
}
```

> Note: `Member::active()` is also used by `MemberOverview` (dashboard). Its meaning now excludes pending members, which is the desired semantics.

### Change 1.3 — Factory

File: `database/factories/MemberFactory.php`

Add `accepted_at` to `definition()` and a `pending()` state:

```php
'accepted_at' => fake()->dateTimeBetween('-1 years', 'now'),
```

```php
public function pending(): self
{
    return $this->state(fn (array $_attributes) => [
        'accepted_at' => null,
    ]);
}
```

### Change 1.4 — Domain entity

File: `app/Domain/Members/Member.php`

Add `name`, `email`, and `acceptedAt` so the acceptance service can build the welcome email payload and stay idempotent:

```php
use DateTimeInterface;

final readonly class Member
{
    public function __construct(
        public MemberId $id,
        public MembershipId $membershipId,
        public bool $isVolunteer,
        public ?HouseholdId $householdId,
        public int $age,
        public string $name,
        public string $email,
        public ?DateTimeInterface $acceptedAt,
    ) {}

    public function isInHousehold(): bool
    {
        return $this->householdId !== null;
    }

    public function isYoungster(): bool
    {
        return $this->age < 18;
    }

    public function isPending(): bool
    {
        return $this->acceptedAt === null;
    }
}
```

### Change 1.5 — Repository

File: `app/Domain/Members/MemberRepository.php`

Add an `accept` method:

```php
#[Autowire]
interface MemberRepository
{
    public function getById(MemberId $memberId): Member;

    public function newMember(NewMember $newMember): MemberId;

    public function getByEmail(string $email): ?MemberId;

    /** Mark the member as accepted (idempotent). */
    public function accept(MemberId $memberId): void;
}
```

File: `app/Infrastructure/Members/MemberDbRepository.php`

Populate the new entity fields in `getById`, create the member as pending in `newMember`, and implement `accept`:

```php
use App\Domain\Members\MemberNameFormatter;
// ...

#[Override]
public function getById(MemberId $memberId): MemberEntity
{
    $model = Member::findOrFail($memberId->value);

    $householdId = $model->household_id;

    return new MemberEntity(
        id: MemberId::create($model->id),
        membershipId: MembershipId::create($model->membership_id),
        isVolunteer: $model->is_volunteer,
        householdId: $householdId ? HouseholdId::create($householdId) : null,
        age: $model->age,
        name: MemberNameFormatter::presentationName(
            $model->first_name,
            $model->infix_name,
            $model->last_name,
        ),
        email: $model->email,
        acceptedAt: $model->accepted_at,
    );
}

#[Override]
public function newMember(NewMember $newMember): MemberId
{
    /** @var Member $member */
    $member = Member::create([
        // ... existing fields ...
        'registration_data' => $newMember->registrationData,
        'accepted_at' => null,
    ]);

    // ... paymentInformation creation unchanged ...

    return MemberId::create($member->id);
}

#[Override]
public function accept(MemberId $memberId): void
{
    $member = Member::query()->find($memberId->value);

    if ($member === null || $member->accepted_at !== null) {
        return;
    }

    $member->accepted_at = now();
    $member->save();
}
```

---

## Change 2 — Registration flow (no account, acknowledgment email)

### Change 2.1 — Stop provisioning in `NewMemberService`

File: `app/Domain/Members/NewMemberService.php`

Remove the `MemberUserRepository` dependency and the `provision()` call:

```php
final readonly class NewMemberService
{
    public function __construct(
        private MemberRepository $memberRepository,
        private MembershipRepository $membershipRepository,
        private Dispatcher $eventDispatcher,
    ) {}

    /** @throws RuntimeException */
    public function fromRegistration(FormData $formData): MemberId
    {
        $newMember = $this->toNewMember($formData);

        $memberId = $this->memberRepository->newMember($newMember);

        $event = $this->toNewMemberRegistrationEvent($memberId, $formData);
        $this->eventDispatcher->dispatch($event);

        return $memberId;
    }

    // ... toNewMemberRegistrationEvent / toNewMember unchanged ...
}
```

### Change 2.2 — Replace the member welcome email with an acknowledgment

Delete:

- `app/Domain/Members/Listeners/SendNewMemberWelcome.php`
- `app/Domain/Registration/Mails/NewMemberWelcome.php`
- `resources/views/mail/new-member-welcome.blade.php`

`NewMemberRegistration` now fires two listeners: `SendAdminNewMemberNotification` (admin notified of a member awaiting review) and a new `SendRegistrationReceived` listener that acknowledges the applicant.

File: `app/Domain/Members/Listeners/SendRegistrationReceived.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Members\Listeners;

use App\Domain\Mail\MailSender;
use App\Domain\Mail\Recipient;
use App\Domain\Registration\Events\NewMemberRegistration;
use App\Domain\Registration\Mails\RegistrationReceived;

final readonly class SendRegistrationReceived
{
    public function __construct(
        private MailSender $mailSender,
    ) {}

    public function handle(NewMemberRegistration $event): void
    {
        $this->mailSender->send(new RegistrationReceived(
            new Recipient(name: $event->memberName, email: $event->memberEmail),
        ));
    }
}
```

File: `app/Domain/Registration/Mails/RegistrationReceived.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Registration\Mails;

use App\Domain\Mail\BaseMail;
use App\Domain\Mail\Recipient;
use Illuminate\Mail\Mailables\Content;
use Override;

final readonly class RegistrationReceived extends BaseMail
{
    public function __construct(
        public Recipient $recipient,
    ) {}

    #[Override]
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.registration-received',
            with: [
                'memberName' => $this->recipient->name,
            ],
        );
    }

    #[Override]
    public function subject(): string
    {
        return 'We hebben je inschrijving ontvangen!';
    }

    #[Override]
    public function to(): Recipient
    {
        return $this->recipient;
    }
}
```

File: `resources/views/mail/registration-received.blade.php` (new)

```blade
@component('mail::message')
# Inschrijving ontvangen!

Beste {{ $memberName }},

Bedankt voor je inschrijving bij Watersportvereniging Almere Centraal.

We hebben je inschrijving ontvangen. We nemen contact met je op zodra we je inschrijving hebben verwerkt.

Met vriendelijke groet,<br>
Almere Centraal ledenadministratie
@endcomponent
```

---

## Change 3 — Acceptance domain service + email

### Change 3.1 — Events

File: `app/Domain/Members/Events/MemberAccepted.php` (new)

Billing listens to this event, so it only needs the member ID:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Members\Events;

use App\Domain\Members\MemberId;

final readonly class MemberAccepted
{
    public function __construct(
        public MemberId $memberId,
    ) {}
}
```

File: `app/Domain/Members/Events/MemberAccountProvisioned.php` (new)

The welcome email listens to this event, so it carries the member name/email:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Members\Events;

use App\Domain\Members\MemberId;

final readonly class MemberAccountProvisioned
{
    public function __construct(
        public MemberId $memberId,
        public string $memberName,
        public string $memberEmail,
    ) {}
}
```

### Change 3.2 — Domain service

File: `app/Domain/Members/AcceptMemberService.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Members;

use App\Domain\Members\Events\MemberAccepted;
use App\Domain\Members\Events\MemberAccountProvisioned;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class AcceptMemberService
{
    public function __construct(
        private MemberRepository $memberRepository,
        private MemberUserRepository $memberUserRepository,
        private Dispatcher $eventDispatcher,
    ) {}

    public function accept(MemberId $memberId): void
    {
        $member = $this->memberRepository->getById($memberId);

        if (!$member->isPending()) {
            return;
        }

        $this->memberRepository->accept($memberId);

        $this->eventDispatcher->dispatch(new MemberAccepted($memberId));

        $this->memberUserRepository->provision($memberId);

        $this->eventDispatcher->dispatch(new MemberAccountProvisioned(
            memberId: $memberId,
            memberName: $member->name,
            memberEmail: $member->email,
        ));
    }
}
```

### Change 3.3 — Mail

File: `app/Domain/Members/Mails/MemberWelcome.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Members\Mails;

use App\Domain\Mail\BaseMail;
use App\Domain\Mail\Recipient;
use Illuminate\Mail\Mailables\Content;
use Override;

final readonly class MemberWelcome extends BaseMail
{
    public function __construct(
        public Recipient $recipient,
        public string $setPasswordUrl,
        public string $loginUrl,
    ) {}

    #[Override]
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.member-welcome',
            with: [
                'memberName' => $this->recipient->name,
                'setPasswordUrl' => $this->setPasswordUrl,
                'loginUrl' => $this->loginUrl,
            ],
        );
    }

    #[Override]
    public function subject(): string
    {
        return 'Welkom als lid bij Watersportvereniging Almere Centraal!';
    }

    #[Override]
    public function to(): Recipient
    {
        return $this->recipient;
    }
}
```

### Change 3.4 — Listener

File: `app/Domain/Members/Listeners/SendMemberWelcome.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Members\Listeners;

use App\Domain\Mail\MailSender;
use App\Domain\Mail\Recipient;
use App\Domain\Members\Events\MemberAccountProvisioned;
use App\Domain\Members\Mails\MemberWelcome;
use App\Domain\Members\MemberPasswordResetLink;
use Filament\Facades\Filament;

final readonly class SendMemberWelcome
{
    public function __construct(
        private MailSender $mailSender,
        private MemberPasswordResetLink $memberPasswordResetLink,
    ) {}

    public function handle(MemberAccountProvisioned $event): void
    {
        $setPasswordUrl = $this->memberPasswordResetLink->generate($event->memberId);

        $this->mailSender->send(new MemberWelcome(
            recipient: new Recipient(name: $event->memberName, email: $event->memberEmail),
            setPasswordUrl: $setPasswordUrl,
            loginUrl: Filament::getLoginUrl(),
        ));
    }
}
```

> `Filament::getLoginUrl()` follows the same domain→Filament dependency pattern already used by `NewMemberAdminNotification` (which references `MemberResource` with a `@phpstan-ignore domain.dependency`). Add the same annotation if PHPStan flags it. If `getLoginUrl()` is unavailable in the mail context, fall back to `Filament::getUrl() . '/login'`.

### Change 3.5 — Email template

File: `resources/views/mail/member-welcome.blade.php` (new)

```blade
@component('mail::message')
# Welkom als lid!

Beste {{ $memberName }},

Gefeliciteerd! Je bent nu lid van Watersportvereniging Almere Centraal.

We hebben een account voor je aangemaakt. Stel hieronder je wachtwoord in om in te loggen op je persoonlijke omgeving.

@component('mail::button', ['url' => $setPasswordUrl])
Stel wachtwoord in
@endcomponent

Inloggen kan via: {{ $loginUrl }}

Mocht je vragen hebben, neem dan gerust contact met ons op.

Met vriendelijke groet,<br>
Almere Centraal ledenadministratie
@endcomponent
```

---

## Change 4 — Defer billing to acceptance

Billing on acceptance is event-driven: a dedicated `ApplyMemberAcceptedBilling` listener reacts to `MemberAccepted` (just as `SendMemberWelcome` reacts to `MemberAccountProvisioned`). The observer only bills members that are already accepted at creation (admin-created members) and keeps its existing field-change billing.

### Change 4.1 — Member observer

File: `app/Observers/MemberObserver.php`

Gate `created` on acceptance. Keep `updated` exactly as before (membership_id / is_volunteer / household_id / stopped_at) — there is **no** `accepted_at` handling in the observer; that is the listener's job:

```php
public function created(Member $member): void
{
    if ($member->accepted_at === null) {
        return; // pending members are billed on acceptance (ApplyMemberAcceptedBilling)
    }

    $this->applyMembershipBilling->apply(
        MemberId::create($member->id),
        MembershipId::create($member->membership_id),
    );

    $this->applyMemberVolunteerBilling->apply(MemberId::create($member->id));
    $this->applySameHouseholdBilling->apply(MemberId::create($member->id));
}

public function updated(Member $member): void
{
    // unchanged: membership_id / is_volunteer / household_id / stopped_at checks
}
```

### Change 4.2 — Billing listener on `MemberAccepted`

File: `app/Domain/Members/Listeners/ApplyMemberAcceptedBilling.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Members\Listeners;

use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyMembershipBilling;
use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyMemberVolunteerBilling;
use App\Domain\Invoices\Billing\BillingItemApplicators\ApplySameHouseholdBilling;
use App\Domain\Members\Events\MemberAccepted;
use App\Domain\Members\MemberRepository;

final readonly class ApplyMemberAcceptedBilling
{
    public function __construct(
        private MemberRepository $memberRepository,
        private ApplyMembershipBilling $applyMembershipBilling,
        private ApplyMemberVolunteerBilling $applyMemberVolunteerBilling,
        private ApplySameHouseholdBilling $applySameHouseholdBilling,
    ) {}

    public function handle(MemberAccepted $event): void
    {
        $member = $this->memberRepository->getById($event->memberId);

        $this->applyMembershipBilling->apply($event->memberId, $member->membershipId);
        $this->applyMemberVolunteerBilling->apply($event->memberId);
        $this->applySameHouseholdBilling->apply($event->memberId);
    }
}
```

### Change 4.3 — Admin-created members are accepted immediately

File: `app/Filament/Admin/Resources/Members/Pages/CreateMember.php`

```php
protected function mutateFormDataBeforeSave(array $data): array
{
    if ($data['infix_name'] === null) {
        $data['infix_name'] = '';
    }

    $data['accepted_at'] = now();

    return $data;
}
```

> `CreateMember` persists the member with `accepted_at` set, so `MemberObserver::created` bills it immediately (admin-created members are already accepted). `AcceptMemberService` handles the registration path: it sets `accepted_at`, dispatches `MemberAccepted` (billing via `ApplyMemberAcceptedBilling`), provisions the account, and dispatches `MemberAccountProvisioned` (welcome via `SendMemberWelcome`).

---

## Change 5 — Filament UI

### Change 5.1 — Accept action (single)

File: `app/Filament/Admin/Resources/Members/Actions/AcceptMemberAction.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Actions;

use App\Domain\Members\AcceptMemberService;
use App\Domain\Members\MemberId;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

final class AcceptMemberAction
{
    public static function make(): Action
    {
        return Action::make('acceptMember')
            ->label(__('labels.accept_member'))
            ->icon(Heroicon::CheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('labels.accept_member_description'))
            ->visible(static fn (Member $record): bool => $record->isPending())
            ->action(static function (Member $record, AcceptMemberService $service): void {
                $service->accept(MemberId::create($record->id));
            })
            ->successNotificationTitle(__('notifications.member_accepted'));
    }
}
```

File: `app/Filament/Admin/Resources/Members/Pages/ViewMember.php`

```php
use App\Filament\Admin\Resources\Members\Actions\AcceptMemberAction;
use Filament\Actions\EditAction;
// ...

#[Override]
protected function getHeaderActions(): array
{
    return [
        AcceptMemberAction::make(),
        EditAction::make(),
    ];
}
```

### Change 5.2 — Tabs with badge counters

File: `app/Filament/Admin/Resources/Members/Pages/ListMembers.php`

```php
use App\Models\Member;
// ...

#[Override]
public function getTabs(): array
{
    return [
        'new' => Tab::make(__('labels.new'))
            ->badge(Member::query()->pending()->count())
            ->modifyQueryUsing(
                static fn (Builder $query) => $query->pending(),
            ),
        'active' => Tab::make(__('labels.active'))
            ->badge(Member::query()->active()->count())
            ->modifyQueryUsing(
                static fn (Builder $query) => $query->active(),
            ),
        'inactive' => Tab::make(__('labels.inactive'))
            ->badge(Member::query()->whereNotNull('stopped_at')->count())
            ->modifyQueryUsing(
                static fn (Builder $query) => $query->whereNotNull('stopped_at'),
            ),
    ];
}
```

### Change 5.3 — Bulk accept action

File: `app/Filament/Admin/Resources/Members/Tables/MembersTable.php`

```php
use App\Domain\Members\AcceptMemberService;
use App\Domain\Members\MemberId;
use Filament\Actions\BulkAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
// ...

->toolbarActions([
    BulkActionGroup::make([
        BulkAction::make('acceptMembers')
            ->label(__('labels.accept_members'))
            ->icon(Heroicon::CheckCircle)
            ->requiresConfirmation()
            ->modalDescription(__('labels.accept_members_description'))
            ->deselectRecordsAfterCompletion()
            ->action(static function (Collection $records, AcceptMemberService $service): void {
                foreach ($records as $record) {
                    $service->accept(MemberId::create($record->id));
                }
            })
            ->successNotificationTitle(__('notifications.members_accepted')),
        DeleteBulkAction::make(),
    ]),
])
```

> The service is idempotent, so selecting an already-accepted member in bulk is a no-op for that member.

### Change 5.4 — Hide `CreateUserAccountAction` for pending members

File: `app/Filament/Admin/Resources/Members/Actions/CreateUserAccountAction.php`

Pending members must be accepted via `AcceptMemberAction`, not by manually creating an account. Update the visibility and dispatch `MemberAccountProvisioned` to send the welcome email (dispatching `MemberAccepted` instead would wrongly re-apply billing):

```php
use App\Domain\Members\Events\MemberAccountProvisioned;
use Illuminate\Events\Dispatcher;
// ... remove the NewMemberRegistration / MembershipData / RegistrationSource imports no longer needed ...

->visible(static fn (Member $record): bool => $record->user_id === null && !$record->isPending())
->action(static function (
    Member $record,
    MemberUserRepository $repository,
    Dispatcher $dispatcher,
): void {
    $memberId = MemberId::create($record->id);
    $repository->provision($memberId);

    $dispatcher->dispatch(new MemberAccountProvisioned(
        memberId: $memberId,
        memberName: MemberNameFormatter::presentationName(
            $record->first_name,
            $record->infix_name,
            $record->last_name,
        ),
        memberEmail: $record->email,
    ));
})
```

---

## Change 6 — Prevent linking entities to pending members

### Change 6.1 — Relation managers become read-only for pending members

File: `app/Filament/Admin/RelationManagers/MemberRelationManager.php`

```php
use App\Models\Member;
// ...

abstract class MemberRelationManager extends RelationManager
{
    // ... existing canViewForRecord ...

    #[Override]
    public function isReadOnly(): bool
    {
        return $this->isOwnerPending();
    }

    protected function isOwnerPending(): bool
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Member && $owner->isPending();
    }
}
```

Remove the now-redundant `isReadOnly(): false` overrides so the base behaviour applies:

- `app/Filament/Admin/Resources/Members/RelationManagers/PurchaseOrdersRelationManager.php`
- `app/Filament/Admin/Resources/Members/RelationManagers/MemberObjectsRelationManager.php`
- `app/Filament/Admin/Resources/Members/RelationManagers/StorageSpaceRentalsRelationManager.php`

Gate the custom `generate` action in `InvoicesRelationManager` explicitly (read-only does not hide arbitrary custom header actions):

File: `app/Filament/Admin/Resources/Members/RelationManagers/InvoicesRelationManager.php`

```php
Action::make('generate')
    ->label(__('labels.generate_invoice'))
    ->visible(static fn (RelationManager $livewire): bool =>
        (auth()->user()?->can('create', Invoice::class) ?? false)
        && !$livewire->getOwnerRecord()->isPending())
    // ...
```

Add the pending guard to the `PurchaseOrdersRelationManager` header `CreateAction` (it uses `->url()`, which is not hidden by read-only):

```php
CreateAction::make()
    ->visible(static fn (RelationManager $livewire): bool =>
        (auth()->user()?->can('create', PurchaseOrder::class) ?? false)
        && !$livewire->getOwnerRecord()->isPending())
    ->url(static fn (RelationManager $livewire): string => PurchaseOrderResource::getUrl('create', [
        'member_id' => $livewire->getOwnerRecord()->getKey(),
    ])),
```

### Change 6.2 — Exclude pending members from every member selector

Add `->accepted()` (or `->whereNotNull('accepted_at')`) to each member query:

- `app/Filament/Admin/Resources/Invoices/Schemas/InvoiceForm.php` — `Select::make('member_id')->relationship('member', modifyQueryUsing: fn (Builder $q) => $q->accepted()->orderBy(...))`.
- `app/Filament/Admin/Resources/PurchaseOrders/Schemas/PurchaseOrderForm.php` — `options(fn () => Member::query()->accepted()->get()->mapWithKeys(...))`.
- `app/Filament/Admin/Resources/StorageSpaces/Schemas/StorageSpaceRentalForm.php` (`forStorageSpace`) — `Select::make('member_id')->relationship('member', 'id', modifyQueryUsing: fn (Builder $q) => $q->accepted())`.
- `app/Filament/Admin/Resources/BankTransactions/Actions/CreateInvoiceFromTransactionAction.php` — `options(fn () => Member::query()->accepted()->orderBy(...)->get()->mapWithKeys(...))`.
- `app/Filament/Admin/Resources/Households/Actions/HouseholdMemberActions.php` (both `member_id` selects) — `Member::query()->whereNull('household_id')->accepted()->get()`.
- `app/Filament/Admin/Resources/Activities/RelationManagers/ActivityMembersRelationManager.php` — `AttachAction::make()->recordSelectOptionsQuery(fn (Builder $query) => $query->accepted())`.

---

## Change 7 — Translations

File: `lang/nl/labels.php`

```php
'new' => 'Nieuw',
'accept_member' => 'Accepteren',
'accept_member_description' => 'Hiermee accepteer je dit lid. Er wordt een account aangemaakt en het lid ontvangt een welkomstmail met instructies om in te loggen.',
'accept_members' => 'Accepteren',
'accept_members_description' => 'Accepteer de geselecteerde leden. Voor elk lid wordt een account aangemaakt en een welkomstmail verstuurd.',
```

File: `lang/nl/notifications.php`

```php
'member_accepted' => 'Lid geaccepteerd; account aangemaakt en welkomstmail verstuurd.',
'members_accepted' => 'Geselecteerde leden geaccepteerd.',
```

---

## Change 8 — Mailbook

File: `routes/mailbook.php`

Remove the `NewMemberWelcome` variant (and its import) and add both the `RegistrationReceived` and `MemberWelcome` variants:

```php
use App\Domain\Members\Mails\MemberWelcome;
use App\Domain\Registration\Mails\RegistrationReceived;
// ...

Mailbook::add(RegistrationReceived::class)
    ->variant(
        'Registration received',
        static fn (): Mailable => new MailMailable(new RegistrationReceived(
            new Recipient('Jan de Vries', 'jan@devries.nl'),
        )),
    );

Mailbook::add(MemberWelcome::class)
    ->variant(
        'Member welcome',
        static fn (): Mailable => new MailMailable(new MemberWelcome(
            new Recipient('Jan de Vries', 'jan@devries.nl'),
            'https://example.test/reset-password/token?email=jan%40example.com',
            'https://example.test/admin/login',
        )),
    );
```

---

## Change 9 — Tests

### New tests

- `tests/Unit/Domain/Members/Listeners/SendRegistrationReceivedTest.php` — mocks `MailSender`; asserts `RegistrationReceived` is sent with the member name/email.
- `tests/Unit/Domain/Registration/Mails/RegistrationReceivedTest.php` — asserts subject, markdown template, and `with` payload.
- `tests/Unit/Domain/Members/AcceptMemberServiceTest.php` — mocks `MemberRepository` (returns a pending `Member` entity, expects `accept`), `MemberUserRepository` (expects `provision`), and `EventDispatcher` (expects `MemberAccepted` then `MemberAccountProvisioned`). Assert that a non-pending member is a no-op. (Billing is asserted separately in `ApplyMemberAcceptedBillingTest`.)
- `tests/Unit/Domain/Members/Listeners/SendMemberWelcomeTest.php` — mocks `MailSender` + `MemberPasswordResetLink`; asserts `MemberWelcome` is sent with the reset URL and login URL on `MemberAccountProvisioned`.
- `tests/Unit/Domain/Members/Listeners/ApplyMemberAcceptedBillingTest.php` — mocks `MemberRepository` (returns a `Member` entity with a `membershipId`) and the three billing applicators (`ApplyMembershipBilling`, `ApplyMemberVolunteerBilling`, `ApplySameHouseholdBilling`); asserts all three are applied on `MemberAccepted`.
- `tests/Unit/Domain/Members/Mails/MemberWelcomeTest.php` — asserts subject, markdown template, and `with` payload (memberName, setPasswordUrl, loginUrl).
- `tests/Feature/Filament/Admin/Resources/Members/AcceptMemberActionTest.php` — action visible only for pending members; calling it creates a `User`, sets `accepted_at`, and sends the welcome mail (`Mail::fake()` + `MailMailable`).
- `tests/Feature/Filament/Admin/Resources/Members/ListMembersTabsTest.php` — asserts the "Nieuw" tab lists only pending members and the badge counts are correct.
- `tests/Feature/Filament/Admin/Resources/Members/AcceptMembersBulkActionTest.php` — bulk accepts selected pending members.
- `tests/Feature/Authorization/MemberPendingRelationManagersTest.php` — for a pending member, the five relation managers are read-only (no create/attach/edit/delete actions); for an accepted member they are writable.

### Updated tests

- `tests/Unit/Domain/Members/NewMemberServiceTest.php` — remove the `MemberUserRepository` mock/expectation and the constructor argument; registration no longer provisions.
- `tests/Unit/Domain/Members/MemberTest.php` — construct `Member` with `name`, `email`, `acceptedAt`; add `isPending()` coverage.
- `tests/Feature/Infrastructure/Members/MemberDbRepositoryTest.php` — `getById` returns `name`/`email`/`acceptedAt`; `newMember` creates a pending member (`accepted_at` null); add an `accept` test (sets `accepted_at`, idempotent).
- `tests/Feature/Observers/MemberObserverTest.php` — add: no billing applied on `created` for a pending member (`accepted_at` null); the existing "applies billing on created members" test still passes (factory members are accepted). The `updated` handler no longer reacts to `accepted_at` (that moved to `ApplyMemberAcceptedBilling`).
- `tests/Feature/Http/Controllers/Registration/RegistrationControllerTest.php` — `test_full_registration_flow_end_to_end` now asserts the member has no `user_id` and `accepted_at` is null; wrap in `Mail::fake()` (the admin notification and the registration acknowledgment are sent).
- `tests/Feature/Filament/Admin/Resources/Members/CreateUserAccountActionTest.php` — hidden for pending members; action provisions the account and dispatches `MemberAccountProvisioned` (asserts welcome mail sent).
- `tests/Unit/Domain/Registration/Events/NewMemberRegistrationTest.php` — no change needed (event unchanged).

### Deleted tests

- `tests/Unit/Domain/Members/Listeners/SendNewMemberWelcomeTest.php`
- `tests/Unit/Domain/Registration/Mails/NewMemberWelcomeTest.php`

### Other updated tests

- `tests/Feature/Http/Controllers/Registration/RegistrationControllerTest.php` — the registration acknowledgment email is now sent on registration (in addition to the admin notification). Update mail assertions where relevant (`Mail::fake()` + `MailMailable` / `RegistrationReceived`).

---

## Open questions / follow-ups

- **Dashboard**: `MemberOverview` still counts `Member::active()`, which now excludes pending members. Optionally add a separate "pending members" stat — not required by the task.
- **Batch invoicing**: because billing is deferred, pending members never hold `BillableItemInstance`s, so `BillableItemsViewDbRepository::listBillableMembers` never returns them. No query change is required; optionally add `->whereNotNull('accepted_at')` to the `whereHas('member', ...)` clause for defence in depth.
