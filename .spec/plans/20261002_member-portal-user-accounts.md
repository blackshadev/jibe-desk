# Implementation Plan: Member Login (Single Panel, Role-Scoped)

**Date:** Fri Oct 02 2026

## Overview

Give every `Member` a `User` login so members can sign in to the existing Filament admin panel and see **only** their own and their household's data. Members can create declarations (purchase orders) for themselves, change their password via the profile page, and see all household members (including themselves) from the dashboard and profile. All admin resources, navigation, and write actions are hidden or denied for members.

This revision uses a **single panel with role-based scoping** (no second panel, no duplicated resources).

## Summary of current situation

- `Member` already has a nullable `user_id` column (unique partial index) and `User` has a `member()` `hasOne` relation. `Member` has no inverse `user()` relation, and nothing provisions a `User`.
- One Filament panel (`app/Providers/Filament/AdminPanelProvider.php`, id `admin`, path `admin`). `User::canAccessPanel()` returns `hasVerifiedEmail()` only.
- Authorization is permission-based (`spatie/laravel-permission`): `ResourcePermission`/`RoleName` enums, `ResourcePolicy` base. Resource pages and relation managers auto-gate standard CRUD actions (`CreateAction`/`EditAction`/`DeleteAction`/`ViewAction`) through the model policy (`Filament\Resources\Pages\Page::getDefaultActionAuthorizationResponse` and `RelationManager::getDefaultActionAuthorizationResponse`).
- `MemberResource` renders a member's related data through 8 relation managers. `PurchaseOrder` belongs to a `Member` (`member_id`); its create page pre-fills creditor data via `MemberCreditorPrefill`.
- `MemberDbRepository::newMember()` is the persistence path for public registration; admin `CreateMember` persists directly via Eloquent.
- Password reset runs through **Fortify** (`Features::resetPasswords()`), queued `QueuedResetPassword` builds a `route('password.reset', ...)` link; after reset Fortify redirects to `config('fortify.home')` (`/home`, which does not exist).
- The admin `MemberForm` has **no email field**, though `members.email` is required.
- The panel does **not** call `->profile()`, so the profile page is not currently enabled.

## Summary of planned changes

1. Add `Member::user()` (BelongsTo) + `Member::visibleMemberIds()` (self + household), and `User::isMember()` / `User::isAdmin()` helpers.
2. Provision a `User` per member in `NewMemberService`: domain `MemberUserRepository` + `MemberUserDbRepository` creates the `User` and links it to the member (no email). The `SendNewMemberWelcome` listener generates the password-reset URL via an injected `PasswordBroker` (bound in `AppServiceProvider`) and sends the existing `NewMemberWelcome` email with the "set your password" link. A manual `CreateUserAccountAction` on the admin member edit page provisions the account and sends the same welcome email (visible only when the member has no account; account status surfaced in the member table + form). The `MemberAccountInvitation` notification and the `ProvisionMemberUser` listener are removed.
3. Make `MemberPolicy` and `PurchaseOrderPolicy` member-aware (view/viewAny scoped to household/self; create for members; edit/delete remain admin-only). Fix the buggy `member_id === user->id` check in `PurchaseOrderPolicy::update/delete`.
4. Scope the `ListMembers` and `ListPurchaseOrders` queries to household/self for members.
5. Gate the few relation-manager actions that are not policy-driven (`Activities` detach, `PurchaseOrders` create).
6. Make the purchase-order create flow member-aware (force `member_id` to self; hide the member selector from members).
7. Add a custom `navigation()` builder: members see only Dashboard, "Mijn huishouden", "Declaraties", and Profiel; admins keep the default navigation.
8. Add a role-aware `Dashboard`, a `Profile` page (password change + household members), and a shared `HouseholdMembersWidget`.
9. Enable `->profile()` and redirect Fortify password resets to the panel.
10. Add labels/translations and tests.

Design decisions (recorded):

- **No new role.** Members are identified by `User::member()` existing (`User::isMember()`). `RoleName` is untouched so `MembersCluster::canAccessClusteredComponents()` (`hasRole(RoleName::cases())`) keeps working for admins only.
- **Authorization via policies + role-awareness in the existing resources**, not a second panel. Standard CRUD actions are already policy-gated by Filament; only a handful of custom/attach/detach actions need explicit gating.
- **Member view shows** personal + membership info (read-only) via the existing `MemberForm` field-level permission gating (payment/address/registration tabs stay admin-only). Household/self members, invoices, purchase orders, activities, objects, storage spaces, emails, and billable-item instances are all shown through the existing relation managers on the member view page.
- **Declarations** = the existing `PurchaseOrderResource`, scoped to the member's own orders; create always assigns to self. Bank-transaction and bookkeeping relation managers on the purchase-order view are auto-hidden for members (their `relatedResource`/`viewAny` policies deny members).

---

## Change 1 — Member model and User helpers

File: `app/Models/Member.php`

Add the inverse relation and the scoping helper.

```php
use App\Models\Pivots\ActivityMember;
use App\Observers\MemberObserver;
// ... existing imports ...

/** @return BelongsTo<User, $this> */
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

/**
 * The member ids visible to this member's login: self + household members.
 *
 * @return list<int>
 */
public function visibleMemberIds(): array
{
    if ($this->household_id === null) {
        return [$this->id];
    }

    return self::query()
        ->where('household_id', $this->household_id)
        ->pluck('id')
        ->map(static fn (int $id): int => $id)
        ->all();
}
```

File: `app/Models/User.php`

Add role/member helpers (used across policies, navigation, and relation managers).

```php
use App\Domain\Authorization\RoleName;
// ... existing imports ...

public function isMember(): bool
{
    return $this->member()->exists();
}

public function isAdmin(): bool
{
    return $this->hasAnyRole(
        array_map(static fn (RoleName $role): string => $role->value, RoleName::cases()),
    );
}
```

`canAccessPanel()` stays as-is (`hasVerifiedEmail()`); both admins and verified members access the single panel.

---

## Change 2 — User provisioning

### Change 2.1 — Domain repository interface

File: `app/Domain/Members/MemberUserRepository.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Members;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface MemberUserRepository
{
    /** Create a login account for the member and link it (no email is sent here). */
    public function provision(MemberId $memberId): void;
}
```

### Change 2.2 — Infrastructure implementation

File: `app/Infrastructure/Members/MemberUserDbRepository.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Members;

use App\Domain\Members\MemberId;
use App\Domain\Members\MemberNameFormatter;
use App\Domain\Members\MemberUserRepository;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Str;
use Override;

final readonly class MemberUserDbRepository implements MemberUserRepository
{
    #[Override]
    public function provision(MemberId $memberId): void
    {
        $member = Member::query()->find($memberId->value);

        if ($member === null || $member->user_id !== null) {
            return;
        }

        if (User::query()->where('email', $member->email)->exists()) {
            return;
        }

        $user = User::create([
            'name' => MemberNameFormatter::presentationName(
                $member->first_name,
                $member->infix_name,
                $member->last_name,
            ),
            'email' => $member->email,
            'password' => Str::password(),
            'email_verified_at' => now(),
        ]);

        $member->forceFill(['user_id' => $user->id])->save();
    }
}
```

### Change 2.3 — Reuse the `NewMemberWelcome` email

File: `app/Domain/Registration/Mails/NewMemberWelcome.php`

Add a `setPasswordUrl` field and pass it to the markdown template.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Registration\Mails;

use App\Domain\Mail\BaseMail;
use App\Domain\Mail\Recipient;
use Illuminate\Mail\Mailables\Content;
use Override;

final readonly class NewMemberWelcome extends BaseMail
{
    public function __construct(
        public Recipient $recipient,
        public string $setPasswordUrl,
    ) {}

    #[Override]
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-member-welcome',
            with: [
                'memberName' => $this->recipient->name,
                'setPasswordUrl' => $this->setPasswordUrl,
            ],
        );
    }

    #[Override]
    public function subject(): string
    {
        return 'Welkom bij Almere Centraal!';
    }

    #[Override]
    public function to(): Recipient
    {
        return $this->recipient;
    }
}
```

File: `resources/views/mail/new-member-welcome.blade.php`

Add the "set your password" button using the link.

```blade
@component('mail::message')
# Welkom bij Almere Centraal!

Beste {{ $memberName }},

Wat leuk dat je lid wilt worden van Watersportvereniging Almere Centraal!

We hebben een account voor je aangemaakt. Klik op onderstaande knop om je wachtwoord in te stellen en in te loggen op je persoonlijke omgeving.

@component('mail::button', ['url' => $setPasswordUrl])
Stel wachtwoord in
@endcomponent

Mocht je in de tussentijd vragen hebben, neem dan gerust contact met ons op.

Met vriendelijke groet,<br>
Almere Centraal ledenadministratie
@endcomponent
```

File: `routes/mailbook.php`

Update the `NewMemberWelcome` variant to pass a `setPasswordUrl` value (e.g. `'https://example.test/reset-password/token?email=jan%40example.com'`).

### Change 2.4 — Provision in `NewMemberService`, generate URL in `SendNewMemberWelcome`

Provisioning moves out of a listener into `NewMemberService`. The `SendNewMemberWelcome` listener now generates the password-reset URL via an injected `PasswordBroker` and sends the welcome email with the link. The `ProvisionMemberUser` listener is removed.

#### Change 2.4.1 — Provision the account in `NewMemberService`

File: `app/Domain/Members/NewMemberService.php`

Inject `MemberUserRepository` and provision after the member is created (before dispatching the event, so the user exists when `SendNewMemberWelcome` runs).

```php
use App\Domain\Members\Events\NewMemberRegistration;
use App\Domain\Registration\FormData;
use Illuminate\Contracts\Events\Dispatcher;
// ...

final readonly class NewMemberService
{
    public function __construct(
        private MemberRepository $memberRepository,
        private MembershipRepository $membershipRepository,
        private MemberUserRepository $memberUserRepository,
        private Dispatcher $eventDispatcher,
    ) {}

    /** @throws RuntimeException */
    public function fromRegistration(FormData $formData): MemberId
    {
        $newMember = $this->toNewMember($formData);

        $memberId = $this->memberRepository->newMember($newMember);

        $this->memberUserRepository->provision($memberId);

        $event = $this->toNewMemberRegistrationEvent($memberId, $formData);
        $this->eventDispatcher->dispatch($event);

        return $memberId;
    }

    // ... rest unchanged ...
}
```

#### Change 2.4.2 — Generate the reset URL in `SendNewMemberWelcome`

File: `app/Domain/Members/Listeners/SendNewMemberWelcome.php`

Inject `MailSender` and `PasswordBroker`, generate the reset URL, and send `NewMemberWelcome`. A reusable `send()` method is exposed so the admin action can send the same email.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Members\Listeners;

use App\Domain\Mail\MailSender;
use App\Domain\Mail\Recipient;
use App\Domain\Members\Events\NewMemberRegistration;
use App\Domain\Registration\Mails\NewMemberWelcome;
use Illuminate\Contracts\Auth\PasswordBroker;

final readonly class SendNewMemberWelcome
{
    public function __construct(
        private MailSender $mailSender,
        private PasswordBroker $passwordBroker,
    ) {}

    public function handle(NewMemberRegistration $event): void
    {
        $this->send($event->memberName, $event->memberEmail);
    }

    public function send(string $name, string $email): void
    {
        $user = $this->passwordBroker->getUser(['email' => $email]);

        if ($user === null) {
            return;
        }

        $setPasswordUrl = url(route('password.reset', [
            'token' => $this->passwordBroker->createToken($user),
            'email' => $user->getEmailForPasswordReset(),
        ], false));

        $this->mailSender->send(new NewMemberWelcome(
            new Recipient(name: $name, email: $email),
            setPasswordUrl: $setPasswordUrl,
        ));
    }
}
```

#### Change 2.4.3 — Bind `PasswordBroker` in the service provider

File: `app/Providers/AppServiceProvider.php`

Add a binding so `PasswordBroker` can be constructor-injected into `SendNewMemberWelcome`:

```php
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Password;
// ...

public function register(): void
{
    // ... existing bindings ...

    $this->app->bind(
        PasswordBroker::class,
        static fn (): PasswordBroker => Password::broker('users'),
    );
}
```

Delete `app/Domain/Members/Listeners/ProvisionMemberUser.php` (provisioning now happens in `NewMemberService`).

### Change 2.5 — Admin: manual "create user account" action

Admin member creation stays as-is (no auto-provisioning). An explicit action provisions the account; the status is surfaced in the table and form.

File: `app/Filament/Admin/Resources/Members/Actions/CreateUserAccountAction.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Actions;

use App\Domain\Members\MemberId;
use App\Domain\Members\MemberUserRepository;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

final class CreateUserAccountAction
{
    public static function make(): Action
    {
        return Action::make('createUserAccount')
            ->label(__('labels.create_user_account'))
            ->icon(Heroicon::Key)
            ->requiresConfirmation()
            ->modalDescription(__('labels.create_user_account_description'))
            ->visible(static fn (Member $record) => $record->user_id === null)
            ->action(static function (
                Member $record,
                MemberUserRepository $repository,
                SendNewMemberWelcome $sendNewMemberWelcome,
            ): void {
                $repository->provision(MemberId::create($record->id));

                $sendNewMemberWelcome->send(
                    MemberNameFormatter::presentationName(
                        $record->first_name,
                        $record->infix_name,
                        $record->last_name,
                    ),
                    $record->email,
                );
            })
            ->successNotificationTitle(__('notifications.user_account_created'));
    }
}
```

Add the required imports to `CreateUserAccountAction.php`: `use App\Domain\Members\MemberNameFormatter;` and `use App\Domain\Members\Listeners\SendNewMemberWelcome;`.

File: `app/Filament/Admin/Resources/Members/Pages/EditMember.php`

```php
use App\Filament\Admin\Resources\Members\Actions\CreateUserAccountAction;
use App\Filament\Admin\Resources\Members\Actions\StopAction;
// ...

#[Override]
protected function getHeaderActions(): array
{
    return [
        ActionGroup::make([
            CreateUserAccountAction::make(),
            StopAction::make(),
        ])
            ->button()
            ->color('gray'),
    ];
}
```

File: `app/Filament/Admin/Resources/Members/Tables/MembersTable.php`

```php
TextColumn::make('user.email')
    ->label(__('labels.account'))
    ->placeholder(__('labels.no_account'))
    ->searchable(),
```

File: `app/Filament/Admin/Resources/Members/Schemas/MemberForm.php`

```php
use Filament\Support\Enums\Operation;
// ...

Tabs\Tab::make(__('labels.membership_information'))
    ->schema([
        // ... existing membership / stopped_at / is_volunteer fields ...

        TextInput::make('user.email')
            ->label(__('labels.account'))
            ->disabled()
            ->visible(static fn (string $operation): bool => $operation === Operation::Edit->value),
    ]),
```

### Change 2.6 — Admin member form email field

File: `app/Filament/Admin/Resources/Members/Schemas/MemberForm.php`

Add a required email field to the "personal information" tab (currently absent, but `members.email` is required):

```php
Tabs\Tab::make(__('labels.personal_information'))
    ->schema([
        TextInput::make('first_name')
            ->label(__('labels.first_name'))
            ->required(),

        TextInput::make('infix_name')
            ->label(__('labels.infix_name')),

        TextInput::make('last_name')
            ->label(__('labels.last_name'))
            ->required(),

        TextInput::make('email')
            ->label(__('labels.email'))
            ->email()
            ->required(),

        // ... existing gender, birthdate, age fields ...
    ]),
```

---

## Change 3 — Member-aware policies

### Change 3.1 — MemberPolicy

File: `app/Policies/MemberPolicy.php`

Allow members to list/view their household; keep create/update/delete admin-only.

```php
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Override;

final class MemberPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'members';
    }

    #[Override]
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_members') || $user->isMember();
    }

    #[Override]
    public function view(User $user, Model $record): bool
    {
        if ($user->can('view_members')) {
            return true;
        }

        return $user->member !== null && in_array($record->getKey(), $user->member->visibleMemberIds(), true);
    }

    // ... field-level methods (viewPaymentInformation etc.) unchanged ...
}
```

### Change 3.2 — PurchaseOrderPolicy

File: `app/Policies/PurchaseOrderPolicy.php`

Allow members to list/view/create their own orders; remove the buggy `member_id === user->id` clause from `update`/`delete`.

```php
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Override;
use Webmozart\Assert\Assert;

final class PurchaseOrderPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'purchase_orders';
    }

    #[Override]
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_purchase_orders') || $user->isMember();
    }

    #[Override]
    public function view(User $user, Model $record): bool
    {
        if ($user->can('view_purchase_orders')) {
            return true;
        }

        return $user->member?->id === $record->member_id;
    }

    #[Override]
    public function create(User $user): bool
    {
        return $user->can('create_purchase_orders') || $user->isMember();
    }

    #[Override]
    public function update(User $user, Model $purchaseOrder): bool
    {
        Assert::isInstanceOf($purchaseOrder, PurchaseOrder::class);
        if ($purchaseOrder->status !== PurchaseOrderStatus::Open) {
            return false;
        }

        return $user->can('update_purchase_orders');
    }

    #[Override]
    public function delete(User $user, Model $purchaseOrder): bool
    {
        Assert::isInstanceOf($purchaseOrder, PurchaseOrder::class);
        if ($purchaseOrder->status !== PurchaseOrderStatus::Open) {
            return false;
        }

        return $user->can('delete_purchase_orders');
    }

    // markAsApproved / markAsPaid / attachBankTransaction / markAsDeclined unchanged (permission-only).
}
```

---

## Change 4 — Query scoping for members

### Change 4.1 — Member list

File: `app/Filament/Admin/Resources/Members/Pages/ListMembers.php`

```php
use Illuminate\Database\Eloquent\Builder;
// ...

#[Override]
protected function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();
    
    if (auth()->user()->isAdmin()) {
        return $query;
    }

    $member = auth()->user()?->member;
    if ($member !== null) {
        $query->whereIn('id', $member->visibleMemberIds());
    }

    return $query;
}
```

The header `CreateAction` is already hidden for members by `MemberPolicy::create`.

### Change 4.2 — Purchase order list

File: `app/Filament/Admin/Resources/PurchaseOrders/Pages/ListPurchaseOrders.php`

```php
use Illuminate\Database\Eloquent\Builder;
// ...

#[Override]
protected function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();
        
    if (auth()->user()->isAdmin()) {
        return $query;
    }

    $member = auth()->user()?->member;
    if ($member !== null) {
        $query->where('member_id', $member->id);
    }

    return $query;
}
```

> Note: the tab `->badge()` counts in `getTabs()` currently call `PurchaseOrder::query()->count()` globally. Scope these to the member too (or hide the tabs for members) so aggregate counts are not leaked.

---

## Change 5 — Relation-manager action gating

Only two relation managers have actions that are **not** already policy-gated and need explicit gating.

### Change 5.1 — Activities detach

File: `app/Filament/Admin/Resources/Members/RelationManagers/ActivitiesRelationManager.php`

`DetachAction` and `DetachBulkAction` only check `isReadOnly()` (no policy), so gate them by the same ability used for attach.

```php
->recordActions([
    DetachAction::make()
        ->visible(static fn (): bool => auth()->user()?->can('update', ActivityModel::class) ?? false)
        ->successNotificationTitle(__('notifications.activity_detached')),
])
->toolbarActions([
    BulkActionGroup::make([
        DetachBulkAction::make()
            ->visible(static fn (): bool => auth()->user()?->can('update', ActivityModel::class) ?? false),
    ]),
]);
```

### Change 5.2 — Purchase orders create (declaration)

File: `app/Filament/Admin/Resources/Members/RelationManagers/PurchaseOrdersRelationManager.php`

Members create declarations from the "Declaraties" nav item (self-scoped), not from a household member's page. Gate the relation-manager create action to admins only:

```php
->headerActions([
    CreateAction::make()
        ->visible(static fn (): bool => auth()->user()?->can('create_purchase_orders') ?? false)
        ->url(static fn (RelationManager $livewire): string => PurchaseOrderResource::getUrl('create', [
            'member_id' => $livewire->getOwnerRecord()->getKey(),
        ])),
]);
```

`can('create_purchase_orders')` checks the spatie permission (admins), not the member-aware policy `create`.

All other member relation-manager actions (invoices generate/create, billable-instance stop/resume, member-object and storage-rental create/edit/delete, household add/remove) are already gated by their model policies or `->visible(can(...))`, so members see them read-only.

---

## Change 6 — Purchase-order create for members

### Change 6.1 — Create page

File: `app/Filament/Admin/Resources/PurchaseOrders/Pages/CreatePurchaseOrder.php`

Force `member_id` to the logged-in member and pre-fill the creditor from their payment info.

```php
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrders\Helpers\MemberCreditorPrefill;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreatePurchaseOrder extends CreateRecord
{
    #[Override]
    protected static string $resource = PurchaseOrderResource::class;

    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['date'] ??= CarbonImmutable::now();
        $data['status'] = PurchaseOrderStatus::Open;

        $member = auth()->user()?->member;
        if ($member !== null) {
            $data['member_id'] = $member->id;
        }

        return $data;
    }

    #[Override]
    protected function afterFill(): void
    {
        $this->data['date'] = CarbonImmutable::now();
        $this->data['status'] = PurchaseOrderStatus::Open;

        $member = $this->resolveMember();
        if ($member === null) {
            return;
        }

        $this->data['member_id'] = $member->id;

        foreach (MemberCreditorPrefill::for($member) as $field => $value) {
            $this->data[$field] = $value;
        }
    }

    private function resolveMember(): ?Member
    {
        $member = auth()->user()?->member;
        if ($member !== null) {
            return $member;
        }

        $memberId = request()->query('member_id');
        if (!is_numeric($memberId)) {
            return null;
        }

        return Member::find((int) $memberId);
    }
}
```

### Change 6.2 — Hide the member selector from members

File: `app/Filament/Admin/Resources/PurchaseOrders/Schemas/PurchaseOrderForm.php`

The `member_id` select (with all members as options) must not render for members:

```php
Select::make('member_id')
    ->label(__('labels.member'))
    ->visible(static fn (): bool => auth()->user()?->can('update_purchase_orders') ?? false)
    ->options(/* ... unchanged ... */)
    // ...
```

The creditor fields already auto-disable for non-admins (`->disabled(fn () => cannot('update_purchase_orders'))`), and `cost_center_id` already hides for users who cannot `viewAny` cost centers — so members see a simplified, self-prefilled create form with no extra changes.

---

## Change 7 — Navigation

File: `app/Providers/Filament/AdminPanelProvider.php`

Add a `navigation()` closure and enable the profile page.

```php
use App\Filament\Admin\Pages\Profile;
use App\Filament\Admin\Resources\Members\MemberResource;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Filament\Support\Icons\Heroicon;
// ...

return $panel
    // ... existing id/path/theme/colors/branding/discovery/middleware/login/default/authMiddleware ...

    ->navigation(function (): NavigationBuilder | bool {
        if (! auth()->user()?->isMember()) {
            return true; // default admin navigation
        }

        return (new NavigationBuilder())
            ->items([
                NavigationItem::make(__('labels.dashboard'))
                    ->icon(Heroicon::Home)
                    ->url(Filament::getUrl())
                    ->sort(-10),
                NavigationItem::make(__('labels.my_household'))
                    ->icon(Heroicon::UserGroup)
                    ->url(MemberResource::getUrl('index'))
                    ->sort(1),
                NavigationItem::make(__('labels.declarations'))
                    ->icon(Heroicon::ShoppingCart)
                    ->url(PurchaseOrderResource::getUrl('index'))
                    ->sort(2),
                NavigationItem::make(__('labels.profile'))
                    ->icon(Heroicon::UserCircle)
                    ->url(Filament::getProfileUrl())
                    ->sort(3),
            ]);
    })
    ->profile(Profile::class, isSimple: false);
```

`MemberResource` / `PurchaseOrderResource` are imported from `App\Filament\Admin\Resources\...`.

---

## Change 8 — Dashboard, profile, and household widget

### Change 8.1 — Dashboard

File: `app/Filament/Admin/Pages/Dashboard.php`

```php
use App\Filament\Admin\Widgets\Dashboard\HouseholdMembersWidget;
use App\Filament\Admin\Widgets\Dashboard\MemberOverview;
use Filament\Pages\Dashboard as BaseDashboard;
use Override;

final class Dashboard extends BaseDashboard
{
    #[Override]
    protected static ?int $navigationSort = -10;

    #[Override]
    public function getWidgets(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        
        $widgets = [];
        
        if ($user->isAdmin()) {
            $widgets[] = MemberOverview::class;
        }
        
        if ($user->isMember()) {
            $widgets[] = HouseholdMembersWidget::class;
        }
        
        return $widgets;
    }
}
```

### Change 8.2 — Household members widget

File: `app/Filament/Admin/Widgets/Dashboard/HouseholdMembersWidget.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Filament\Admin\Resources\Members\MemberResource;
use App\Models\Member;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class HouseholdMembersWidget extends TableWidget
{
    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->heading(__('labels.household_members'))
            ->query(
                static fn (): Builder => Member::query()
                    ->whereIn('id', auth()->user()?->member?->visibleMemberIds() ?? []),
            )
            ->columns([
                TextColumn::make('name')->label(__('labels.name')),
                TextColumn::make('membership.name')->label(__('labels.membership')),
                TextColumn::make('age')->label(__('labels.age')),
            ])
            ->recordUrl(
                static fn (Member $record): string => MemberResource::getUrl('view', ['record' => $record]),
            );
    }
}
```

### Change 8.3 — Profile page

File: `app/Filament/Admin/Pages/Profile.php` (new)

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\Dashboard\HouseholdMembersWidget;
use Filament\Auth\Pages\EditProfile;
use Override;

final class Profile extends EditProfile
{
    #[Override]
    protected static ?string $navigationLabel = 'Profiel';

    #[Override]
    protected function getFooterWidgets(): array
    {
        return [
            HouseholdMembersWidget::class,
        ];
    }
}
```

Because the panel registers `->profile(Profile::class, isSimple: false)`, the profile renders in the full page layout, which renders `getFooterWidgets()`.

---

## Change 9 — Fortify reset redirect

File: `app/Providers/FortifyServiceProvider.php`

```php
public function boot(): void
{
    Fortify::redirects('reset-password', '/admin');

    // ... existing update/reset/2FA/rate-limiters ...
}
```

---

## Change 10 — Labels and translations

File: `lang/nl/labels.php`

```php
'profile' => 'Profiel',
'my_household' => 'Mijn huishouden',
'declaration' => 'Declaratie',
'declarations' => 'Declaraties',
'new_declaration' => 'Nieuwe declaratie',
'account' => 'Account',
'no_account' => 'Geen account',
'create_user_account' => 'Account aanmaken',
'create_user_account_description' => 'Hiermee wordt een login voor dit lid aangemaakt en ontvangt het lid een e-mail om een wachtwoord in te stellen.',
```

The invitation email copy now lives in the `mail.new-member-welcome` markdown template (see Change 2.3), so no `texts.php` changes are needed.

File: `lang/nl/notifications.php`

```php
'user_account_created' => 'Account aangemaakt; het lid ontvangt een e-mail om een wachtwoord in te stellen',
```

---

## Change 11 — Tests

Create `tests/Feature/Infrastructure/Members/MemberUserDbRepositoryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Members;

use App\Domain\Members\MemberId;
use App\Infrastructure\Members\MemberUserDbRepository;
use App\Models\Member;
use App\Models\User;
use Tests\FeatureTestCase;

final class MemberUserDbRepositoryTest extends FeatureTestCase
{
    public function test_it_creates_a_user_and_links_it_to_the_member(): void
    {
        $member = Member::factory()->createQuietly();
        app(MemberUserDbRepository::class)->provision(MemberId::create($member->id));

        $member->refresh();

        static::assertNotNull($member->user_id);
        $user = User::findOrFail($member->user_id);
        static::assertSame($member->email, $user->email);
        static::assertNotNull($user->email_verified_at);
    }

    public function test_it_is_a_noop_when_a_user_already_exists(): void
    {
        $user = User::factory()->createQuietly();
        $member = Member::factory()->createQuietly(['user_id' => $user->id]);

        app(MemberUserDbRepository::class)->provision(MemberId::create($member->id));

        static::assertSame($user->id, $member->fresh()->user_id);
    }
}
```

Create `tests/Feature/Filament/Admin/Resources/Members/CreateUserAccountActionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources\Members;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Resources\Members\Pages\EditMember;
use App\Infrastructure\Mail\MailMailable;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class CreateUserAccountActionTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_action_is_visible_without_account_and_hidden_with_account(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        $member = Member::factory()->createQuietly();

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->assertActionVisible('createUserAccount');

        $user = User::factory()->createQuietly();
        $member->update(['user_id' => $user->id]);

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->assertActionHidden('createUserAccount');
    }

    public function test_action_provisions_a_user_and_sends_welcome_mail(): void
    {
        Mail::fake();

        $this->withUserHavingRole(RoleName::MemberAdministration);
        $member = Member::factory()->createQuietly();

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->callAction('createUserAccount');

        $member->refresh();
        static::assertNotNull($member->user_id);

        Mail::assertSent(MailMailable::class);
    }
}
```

Update `tests/Unit/Domain/Registration/Mails/NewMemberWelcomeTest.php` — the `NewMemberWelcome` constructor now requires a `setPasswordUrl` argument; add it to each instantiation and add an assertion that the content exposes the URL:

```php
$mail = new NewMemberWelcome(
    new Recipient('Vries, Jan de', 'jan@example.com'),
    'https://example.test/reset-password/abc?email=jan%40example.com',
);

$content = $mail->content();
static::assertSame('mail.new-member-welcome', $content->markdown);
static::assertSame('Vries, Jan de', $content->with['memberName']);
static::assertSame('https://example.test/reset-password/abc?email=jan%40example.com', $content->with['setPasswordUrl']);
```

Create `tests/Feature/Authorization/MemberPortalScopingTest.php` covering member authorization and scoping:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Admin\Resources\Members\Pages\ListMembers;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Models\Member;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\FeatureTestCase;

final class MemberPortalScopingTest extends FeatureTestCase
{
    private function memberUser(): User
    {
        $user = User::factory()->createQuietly();
        Member::factory()->createQuietly(['user_id' => $user->id]);

        return $user;
    }

    public function test_member_sees_only_own_and_household_members(): void
    {
        $user = $this->memberUser();
        $this->actingAs($user);

        $own = $user->member;
        $household = Member::factory()->createQuietly();
        $other = Member::factory()->createQuietly();

        $own->update(['household_id' => 1]);
        $household->update(['household_id' => 1]);

        Livewire::test(ListMembers::class)
            ->assertSee([$own->name, $household->name])
            ->assertDontSee($other->name);
    }

    public function test_member_cannot_view_admin_only_resources(): void
    {
        $this->actingAs($this->memberUser());

        Livewire::test(ListInvoices::class)->assertForbidden();
    }

    public function test_member_can_view_own_purchase_order_but_not_others(): void
    {
        $user = $this->memberUser();
        $this->actingAs($user);

        $own = PurchaseOrder::factory()->create(['member_id' => $user->member->id]);
        $other = PurchaseOrder::factory()->create();

        static::assertTrue(Gate::allows('view', $own));
        static::assertFalse(Gate::allows('view', $other));
        static::assertFalse(Gate::allows('update', $own));
        static::assertFalse(Gate::allows('delete', $own));
    }

    public function test_member_create_purchase_order_is_assigned_to_self(): void
    {
        $user = $this->memberUser();
        $this->actingAs($user);

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm([
                'description' => 'Declaratie',
                'date' => '2026-10-02',
                'image_path' => \Illuminate\Http\UploadedFile::fake()->image('bon.jpg'),
                'lines' => [
                    ['description' => 'Materiaal', 'price' => 25],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('purchase_orders', [
            'member_id' => $user->member->id,
            'description' => 'Declaratie',
        ]);
    }
}
```

### Existing tests to update

- `tests/Unit/Domain/Members/NewMemberServiceTest.php` — `NewMemberService` now takes a `MemberUserRepository` dependency and calls `provision()`; add a `MemberUserRepository` mock (expectation) and pass it to the constructor.
- `tests/Unit/Domain/Members/Listeners/SendNewMemberWelcomeTest.php` — `SendNewMemberWelcome` now takes `PasswordBroker` and generates the reset URL; mock `MailSender` + `PasswordBroker` and assert `NewMemberWelcome` is sent with the expected `setPasswordUrl`.
- `tests/Feature/Infrastructure/Members/MemberDbRepositoryTest.php` — public registration now provisions a user (via `NewMemberService`) and sends the welcome mail (via `SendNewMemberWelcome`); wrap the full registration flow in `Mail::fake()` where relevant.
- Any full-registration-flow tests (`tests/Feature/Http/Controllers/Registration/RegistrationControllerTest.php`, scenario tests using `TestsMemberLifecycle::registerMember()`) should use `Mail::fake()`.
- `tests/Unit/Domain/Registration/Mails/NewMemberWelcomeTest.php` — constructor now requires `setPasswordUrl` (see Change 11).
- `routes/mailbook.php` — the `NewMemberWelcome` variant now passes a `setPasswordUrl` value.
- `tests/Feature/Authorization/AuthorizationTest.php::test_user_without_role_cannot_access_resources` still passes (a role-less, member-less user is denied by `viewAny`).

---

## Open questions / follow-ups

- **Panel path/branding**: the single panel stays at `/admin`. If members should land on a neutral path, the panel path and `brandName` can be adjusted (affects existing URLs/tests).
- **Existing members** have no `User` (no backfill command per product decision). The admin `CreateUserAccountAction` covers them one-by-one. Consider a later "resend invitation / bulk provision" action if needed.
- **Address visibility for members**: the member view reuses `MemberForm` field-level gating, so address/payment/registration stay admin-only. If members should see their own address, add a member-aware branch to the relevant `MemberPolicy` field methods.
