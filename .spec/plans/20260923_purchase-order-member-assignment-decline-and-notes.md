# Purchase order: member assignment, decline reason, and notes

Date: 2026-09-23

## Overview

Extend the PurchaseOrder domain with three capabilities:

- Assign a purchase order to a Member. When assigned, the member's name and IBAN are automatically written to the purchase order's existing creditor fields (`creditor_name`, `creditor_iban`) and those fields are locked while a member is assigned.
- View the assigned purchase orders on the Member resource via a read-only relation manager that also offers a create action (new purchase order created directly for that member, with the same auto-filled and locked creditor data).
- Decline a purchase order from the `Open` state via a header action with a required "declined reason" textarea. The reason is stored and viewable afterwards.
- Add a free-text `notes` field to the purchase order for users to describe their purchase order.

## Current situation

The codebase is a Laravel 13 + Filament v5 admin panel (WSV Almere Centraal) built hexagonally: pure domain in `app/Domain/PurchaseOrders/`, Eloquent adapters in `app/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDb.php` and `app/Models/PurchaseOrder.php`, UI in `app/Filament/Admin/Resources/PurchaseOrders/`.

Current relevant facts:

- `purchase_orders` has columns `description`, `date`, `status`, `image_path`, `creditor_name`, `creditor_iban` (`database/migrations/2026_06_26_203536_create_purchase_orders_table.php`). No member link, no notes, no declined reason.
- `PurchaseOrderStatus` (`app/Domain/PurchaseOrders/PurchaseOrderStatus.php`) has `Open`, `Pending`, `Paid`, `Declined`. `Declined` is currently only reachable automatically through `BankTransactionServiceImpl::linkReversal()` (SEPA chargeback). There is no manual decline action for purchase orders (invoices have one, see `app/Filament/Admin/Resources/Invoices/Pages/ViewInvoice.php`).
- State transitions go through `PurchaseOrderService` / `PurchaseOrderRepository` `markAsPending|markAsPaid|markAsDeclined(PurchaseOrderIdList)` implemented as bulk updates in `PurchaseOrderRepositoryDb`.
- `PurchaseOrderPolicy` allows `update`/`delete` only while status is `Open`, so member assignment and notes editing are naturally limited to the `Open` state.
- Members carry no IBAN themselves; the member IBAN is `payment_information.banking_account_number` (`app/Models/PaymentInformation.php`, one-to-one with `Member::paymentInformation()`, soft-deleted mandates are excluded). The member display name is the `name` accessor built by `MemberNameFormatter::displayName()` ("Lastname, Firstname infix"); `MemberNameFormatter::presentationName()` builds "Firstname infix Lastname".
- No assignee pattern exists yet; the closest conventions are `HouseholdMemberActions` (modal action with a `Select::make('member_id')` options closure keyed by `$member->name`) and `MemberObjectsRelationManager` / `InvoicesRelationManager` (member relation managers).
- Filament conventions: form schema classes in `Schemas/<Name>Form.php`, tables in `Tables/<Name>sTable.php`, state actions as static factory classes in `Actions/`, all UI strings in `lang/nl/labels.php` and `lang/nl/notifications.php`, Dutch labels ("Inkooporder", "Lid", "Geweigerd").
- Tests: PHPUnit (`tests/FeatureTestCase.php`, `tests/UnitTestCase.php`), Mockery Expectation wrapper classes in `tests/Unit/Domain/PurchaseOrders/`, `Livewire::test(...)->fillForm()->call()->assertHasNoFormErrors()` for Filament pages in `tests/Feature/Filament/Admin/Resources/PurchaseOrderResourceTest.php`.

## Planned changes

- Three migrations on `purchase_orders`: nullable `member_id` FK (null on member delete, keeps history), nullable `notes` text, nullable `declined_reason` text.
- Domain layer: `markAsDeclined` gains an optional `?string $reason = null` on `PurchaseOrderService`, `PurchaseOrderServiceImpl`, `PurchaseOrderRepository`, `PurchaseOrderRepositoryDb`; `markAsPending` clears `declined_reason`. The banking reversal caller passes `null` explicitly.
- Models: `PurchaseOrder::member()` belongsTo and `Member::purchaseOrders()` hasMany.
- Purchase order form: member `Select` with live auto-fill of `creditor_name` / `creditor_iban`, creditor fields locked (but still saved) while a member is assigned; `notes` textarea; read-only `declined_reason` textarea shown when declined.
- Purchase order table and list page: member / notes / declined-reason columns and a "declined" tab.
- New `markAsDeclined` header action in `PurchaseOrderStateActions` with a required reason textarea modal, dispatched on both Edit and View pages.
- New `PurchaseOrdersRelationManager` on the member resource: read-only table of assigned purchase orders plus a header `CreateAction` that creates a purchase order pre-assigned to the member.
- Labels, notifications, `PurchaseOrderStatusLabels` gains `declined`, factory states, feature and unit tests, and small updates to the existing context docs.

## Database migrations

Create with `./Taskfile artisan make:migration --no-interaction` (one concern per migration).

`database/migrations/xxxx_add_member_id_to_purchase_orders_table.php`:

```php
public function up(): void
{
    Schema::table('purchase_orders', static function (Blueprint $table): void {
        $table->foreignId('member_id')
            ->nullable()
            ->constrained('members')
            ->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('purchase_orders', static function (Blueprint $table): void {
        $table->dropConstrainedForeignId('member_id');
    });
}
```

`database/migrations/xxxx_add_notes_to_purchase_orders_table.php`:

```php
public function up(): void
{
    Schema::table('purchase_orders', static function (Blueprint $table): void {
        $table->text('notes')->nullable();
    });
}

public function down(): void
{
    Schema::table('purchase_orders', static function (Blueprint $table): void {
        $table->dropColumn('notes');
    });
}
```

`database/migrations/xxxx_add_declined_reason_to_purchase_orders_table.php`:

```php
public function up(): void
{
    Schema::table('purchase_orders', static function (Blueprint $table): void {
        $table->text('declined_reason')->nullable();
    });
}

public function down(): void
{
    Schema::table('purchase_orders', static function (Blueprint $table): void {
        $table->dropColumn('declined_reason');
    });
}
```

## Domain layer: decline reason plumbing

`app/Domain/PurchaseOrders/PurchaseOrderService.php` and `app/Domain/PurchaseOrders/PurchaseOrderRepository.php` — extend the declined signature:

```php
public function markAsDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void;
```

`app/Domain/PurchaseOrders/PurchaseOrderServiceImpl.php`:

```php
#[Override]
public function markAsDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void
{
    $this->repository->markAsDeclined($ids, $reason);
}
```

`app/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDb.php`:

```php
#[Override]
public function markAsDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void
{
    PurchaseOrder::query()
        ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
        ->update([
            'status' => PurchaseOrderStatus::Declined,
            'declined_reason' => $reason,
        ]);
}

#[Override]
public function markAsPending(PurchaseOrderIdList $ids): void
{
    PurchaseOrder::query()
        ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
        ->update([
            'status' => PurchaseOrderStatus::Pending,
            'declined_reason' => null,
        ]);
}
```

`app/Domain/BankTransactions/BankTransactionServiceImpl.php` (`linkReversal()`, currently `$this->purchaseOrderService->markAsDeclined($purchaseOrderIds);`) — pass the reason explicitly so calls are uniform:

```php
$this->purchaseOrderService->markAsDeclined($purchaseOrderIds, null);
```

## Models

`app/Models/PurchaseOrder.php` — add the relation and property PHPDoc (`#[Guarded]` keeps new columns mass assignable automatically):

```php
/**
 * @property PurchaseOrderStatus $status
 * @property DateTimeInterface $date
 * @property ?string $notes
 * @property ?string $declined_reason
 * @property ?int $member_id
 */
// ...

/** @return BelongsTo<Member, $this> */
public function member(): BelongsTo
{
    return $this->belongsTo(Member::class);
}
```

`app/Models/Member.php` — add next to `invoices()`:

```php
/** @return HasMany<PurchaseOrder, $this> */
public function purchaseOrders(): HasMany
{
    return $this->hasMany(PurchaseOrder::class);
}
```

## Purchase order form: member assignment, auto-fill and lock, notes, declined reason

`app/Filament/Admin/Resources/PurchaseOrders/Schemas/PurchaseOrderForm.php` — add a `withMemberField` parameter (the member relation-manager create modal hides the member select because the member is the owner record), put the member select at the top of the "Crediteur" section, and add `notes` / `declined_reason`.

New helper (mirrors `BankTransactions/Helpers/IsOpen.php` style) `app/Filament/Admin/Resources/PurchaseOrders/Helpers/MemberCreditorPrefill.php` so the fill logic is shared by the form and the relation manager create action:

```php
final class MemberCreditorPrefill
{
    /** @return array{creditor_name: non-falsy-string, creditor_iban: ?string} */
    public static function for(Member $member): array
    {
        $member->loadMissing('paymentInformation');

        return [
            'creditor_name' => MemberNameFormatter::presentationName(
                $member->first_name,
                $member->infix_name,
                $member->last_name,
            ),
            'creditor_iban' => $member->paymentInformation?->banking_account_number,
        ];
    }
}
```

Form changes (full creditor section replaced, `notes` added to the information section, `declined_reason` below `status`):

```php
public static function configure(Schema $schema, bool $withMemberField = true): Schema
{
    return $schema
        ->columns()
        ->components([
            Section::make(__('labels.purchase_order_information'))
                ->schema([
                    // ... existing date / description / status ...
                    Textarea::make('declined_reason')
                        ->label(__('labels.declined_reason'))
                        ->rows(4)
                        ->columnSpanFull()
                        ->disabled()
                        ->dehydrated()
                        ->visible(static fn (Get $get): bool => $get('status') === PurchaseOrderStatus::Declined->value),
                    Textarea::make('notes')
                        ->label(__('labels.notes'))
                        ->rows(5)
                        ->columnSpanFull(),
                    // ... existing FileUpload ...
                ]),
            Section::make(__('labels.creditor_information'))
                ->schema([
                    Select::make('member_id')
                        ->label(__('labels.member'))
                        ->visible($withMemberField)
                        ->options(static fn (): array => Member::query()
                            ->get()
                            ->mapWithKeys(static fn (Member $member): array => [$member->id => $member->name])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(static function (?string $state, Set $set): void {
                            if ($state === null) {
                                return; // unassigning keeps the current creditor values and unlocks them
                            }

                            $member = Member::query()->find($state);
                            if ($member === null) {
                                return;
                            }

                            foreach (MemberCreditorPrefill::for($member) as $field => $value) {
                                $set($field, $value);
                            }
                        }),
                    TextInput::make('creditor_name')
                        ->label(__('labels.name'))
                        ->disabled(static fn (Get $get): bool => filled($get('member_id')))
                        ->dehydrated(),
                    TextInput::make('creditor_iban')
                        ->label(__('labels.iban'))
                        ->rule(new Iban())
                        ->disabled(static fn (Get $get): bool => filled($get('member_id')) && filled($get('creditor_iban')))
                        ->dehydrated(),
                ]),
            // ... existing purchase_order_lines section unchanged ...
        ]);
}
```

Behavior notes:

- `creditor_name` and `creditor_iban` must be `->dehydrated()` because disabled fields are otherwise dropped from the saved state.
- `creditor_name` is locked whenever a member is assigned. `creditor_iban` is only locked when a value is present, so the IBAN stays manually fillable when the member has no SEPA mandate (`payment_information` row missing or soft-deleted).
- Clearing the member select keeps the previously filled creditor values but unlocks the fields again.
- `notes` is freely editable (only while `Open`, per `PurchaseOrderPolicy::update`); `declined_reason` is never edited through the form (it is set by the decline action) and is shown read-only when the status is `Declined`.

## Purchase order table and list page

`app/Filament/Admin/Resources/PurchaseOrders/Tables/PurchaseOrdersTable.php` — add columns (member after `creditor_name`, `declined_reason` and `notes` near the end):

```php
TextColumn::make('member.name')
    ->label(__('labels.member'))
    ->searchable()
    ->sortable(),
TextColumn::make('declined_reason')
    ->label(__('labels.declined_reason'))
    ->limit(30)
    ->toggleable(isToggledHiddenByDefault: true),
TextColumn::make('notes')
    ->label(__('labels.notes'))
    ->limit(50)
    ->toggleable(isToggledHiddenByDefault: true),
```

`app/Filament/Admin/Resources/PurchaseOrders/Pages/ListPurchaseOrders.php` — add a declined tab next to the existing all/open/pending/paid tabs (mirrors `ListInvoices`):

```php
'declined' => Tabs\Tab::make(__('labels.purchase_order_status.declined'))
    ->modifyQueryUsing(
        static fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Declined),
    ),
```

`app/Filament/Admin/Labels/PurchaseOrderStatusLabels.php` — add the missing case so the disabled status select renders "Geweigerd":

```php
PurchaseOrderStatus::Declined->value => __('labels.purchase_order_status.declined'),
```

## Decline action

`app/Filament/Admin/Resources/PurchaseOrders/Actions/PurchaseOrderStateActions.php` — add a `markAsDeclined` action (modal with required reason textarea, mirrors the invoice decline action placement but with a reason field):

```php
use Filament\Forms\Components\Textarea;

// inside make(): ...
Action::make('markAsDeclined')
    ->label(__('labels.mark_as_declined'))
    ->icon('heroicon-m-x-circle')
    ->color('danger')
    ->modalHeading(__('labels.mark_as_declined'))
    ->modalDescription(__('labels.manual_mark_purchase_order_declined_warning'))
    ->schema([
        Textarea::make('declined_reason')
            ->label(__('labels.declined_reason'))
            ->rows(4)
            ->required(),
    ])
    ->visible(static fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Open)
    ->action(static function (PurchaseOrder $record, PurchaseOrderService $service, array $data): void {
        $service->markAsDeclined(
            PurchaseOrderIdList::fromArray([$record->id]),
            (string) $data['declined_reason'],
        );
    })
    ->successRedirectUrl(static fn (Page $livewire, PurchaseOrder $record) => (
        $livewire instanceof EditRecord ? PurchaseOrderResource::getUrl('view', ['record' => $record]) : null
    ))
    ->after(static fn (Page $livewire) => $livewire->dispatch('markedAsDeclined'))
    ->successNotificationTitle(__('notifications.purchase_order_marked_declined')),
```

`app/Filament/Admin/Resources/PurchaseOrders/Pages/ViewPurchaseOrder.php` — extend the refresh listener:

```php
#[Override]
#[On('markedAsPaid')]
#[On('markedAsPending')]
#[On('markedAsDeclined')]
public function refresh(): void
{
}
```

`app/Filament/Admin/Resources/PurchaseOrders/Pages/EditPurchaseOrder.php` needs no change (the action redirects to the view page from `EditRecord`, same as `markAsPending`). `ViewPurchaseOrder` and `EditPurchaseOrder` already spread `PurchaseOrderStateActions::make()` into their header actions, so the decline action appears on both.

## Member resource: assigned purchase orders relation manager

New file `app/Filament/Admin/Resources/Members/RelationManagers/PurchaseOrdersRelationManager.php` (read-only table plus header create action; follows `InvoicesRelationManager` and `MemberObjectsRelationManager`):

```php
final class PurchaseOrdersRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'purchaseOrders';

    #[Override]
    protected static ?string $relatedResource = PurchaseOrderResource::class;

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')->label(__('labels.date'))->date()->sortable(),
                TextColumn::make('description')->label(__('labels.description'))->limit(50),
                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->formatStateUsing(static fn (PurchaseOrderStatus $state) => __('labels.purchase_order_status.' . $state->value)),
                TextColumn::make('total')
                    ->label(__('labels.total'))
                    ->formatStateUsing(static fn (CompoundPrice $state) => (string) $state)
                    ->alignEnd(),
                TextColumn::make('declined_reason')
                    ->label(__('labels.declined_reason'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->label(__('labels.notes'))
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(ViewOrEdit::route(PurchaseOrderResource::class))
            ->headerActions([
                CreateAction::make()
                    ->schema(static fn (Schema $schema): Schema => PurchaseOrderForm::configure($schema, withMemberField: false))
                    ->mutateFormDataUsing(static function (array $data, RelationManager $livewire): array {
                        /** @var Member $member */
                        $member = $livewire->getOwnerRecord();
                        $data['member_id'] = $member->id;
                        $data['status'] = PurchaseOrderStatus::Open->value;
                        $data['date'] ??= CarbonImmutable::now()->toDateString();

                        return array_merge($data, MemberCreditorPrefill::for($member));
                    }),
            ]);
    }

    #[Override]
    public function isReadOnly(): bool
    {
        return true;
    }

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.purchase_orders');
    }

    // getModelLabel / getPluralModelLabel as in MemberObjectsRelationManager:
    // mb_strtolower(__('labels.purchase_order')) / mb_strtolower(__('labels.purchase_orders'))
}
```

Notes on this step:

- No edit/delete actions and no toolbar actions: the assigned purchase orders are viewed here and managed on their own resource (the row URL routes to `ViewOrEdit`), which matches the requested "read-only relation manager with a create action".
- The create modal reuses `PurchaseOrderForm` (including the `lines` repeater) with `withMemberField: false`, and forces `status = Open` plus the creditor prefill and `member_id` from the owner member. `member_id` is also set automatically by the relation manager's `CreateAction` for a hasMany relationship; setting it explicitly in `mutateFormDataUsing` keeps the behavior obvious and testable.
- If the `Repeater::make('lines')->relationship()` misbehaves inside the action modal (see Risks), fall back to a header `Action` that redirects to `PurchaseOrderResource::getUrl('create', ['member_id' => $member->id])` and prefill from that query parameter in `CreatePurchaseOrder::afterFill()`.

`app/Filament/Admin/Resources/Members/MemberResource.php` — register the manager (keep the existing order style, right after invoices):

```php
return [
    HouseholdMembersRelationManager::make(),
    InvoicesRelationManager::make(),
    PurchaseOrdersRelationManager::make(),
    BillableItemInstancesRelationManager::make(),
    // ... unchanged ...
];
```

## Labels and notifications

`lang/nl/labels.php` (near the existing purchase-order keys around line 234):

```php
'notes' => 'Notities',
'declined_reason' => 'Reden van weigering',
'manual_mark_purchase_order_declined_warning' => 'Weet je zeker dat je deze inkooporder handmatig als geweigerd wilt markeren? De reden van weigering wordt opgeslagen en getoond.',
```

(`labels.mark_as_declined`, `labels.member`, `labels.iban`, `labels.name`, `labels.purchase_order_status.declined` already exist.)

`lang/nl/notifications.php` (next to `purchase_order_marked_paid`):

```php
'purchase_order_marked_declined' => 'Inkooporder gemarkeerd als geweigerd',
```

## Factories

`database/factories/PurchaseOrderFactory.php` — add states:

```php
public function declined(?string $reason = null): self
{
    return $this->state([
        'status' => PurchaseOrderStatus::Declined,
        'declined_reason' => $reason ?? fake()->sentence(),
    ]);
}

public function forMember(Member $member): self
{
    return $this->state(['member_id' => $member->id]);
}
```

## Tests

Run with `./Taskfile artisan test --compact <file>` and `--filter` per test.

Unit tests (`tests/Unit/Domain/PurchaseOrders/`):

- `PurchaseOrderServiceExpectation.php` and `PurchaseOrderRepositoryExpectation.php` — extend (the repository expectation currently has no `expectsMarkAsDeclined` at all):

```php
public function expectsMarkAsDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void
{
    $this->mock
        ->expects('markAsDeclined')
        ->with(equalTo($ids), equalTo($reason));
}
```

- `PurchaseOrderServiceImplTest.php` — adjust the declined test to pass a reason through and add a null-reason variant:

```php
public function testMarkAsDeclinedStoresReasonOnRepository(): void
{
    $ids = PurchaseOrderIdList::fromArray([1]);
    $this->repo->expectsMarkAsDeclined($ids, 'Niet geautoriseerd door penningmeester');

    $this->service->markAsDeclined($ids, 'Niet geautoriseerd door penningmeester');
}
```

- `tests/Unit/Domain/BankTransactions/BankTransactionServiceImplTest.php` — existing `expectsMarkAsDeclined($ids)` calls keep working since the default `?string $reason = null` matches the explicit `null` now passed by `linkReversal()`; run this test to confirm.

Feature tests (`tests/Feature/Filament/Admin/Resources/PurchaseOrderResourceTest.php`) — extend the existing class (same style as `test_can_create_purchase_order_with_lines`, snake_case method names here to match the file's existing style):

```php
public function test_assigning_member_fills_creditor_name_and_iban(): void
{
    $this->withAuthorizedUser();
    $member = Member::factory()->has(PaymentInformation::factory()->state([
        'banking_account_number' => 'NL02ABNA0123456789',
    ]))->createQuietly();

    Livewire::test(CreatePurchaseOrder::class)
        ->fillForm(['member_id' => $member->id])
        ->assertFormSet([
            'creditor_iban' => 'NL02ABNA0123456789',
        ])
        ->assertFormFieldDisabled('creditor_name');

    // save with a line and assertDatabaseHas('purchase_orders', [... 'member_id' => $member->id ...])
}

public function test_declining_purchase_order_stores_reason(): void
{
    $this->withAuthorizedUser();
    $po = PurchaseOrder::factory()->open()->create();

    Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getRouteKey()])
        ->callAction('markAsDeclined', data: ['declined_reason' => 'Factuur overtrof het budget']);

    $po->refresh();
    static::assertSame(PurchaseOrderStatus::Declined, $po->status);
    static::assertSame('Factuur overtrof het budget', $po->declined_reason);
}

public function test_decline_reason_is_required(): void
{
    $this->withAuthorizedUser();
    $po = PurchaseOrder::factory()->open()->create();

    Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getRouteKey()])
        ->callAction('markAsDeclined', data: ['declined_reason' => ''])
        ->assertActionHasErrors(['declined_reason' => 'required']);
}

public function test_cannot_decline_purchase_order_when_not_open(): void
{
    $this->withAuthorizedUser();
    $po = PurchaseOrder::factory()->pending()->create();

    Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getRouteKey()])
        ->assertActionHidden('markAsDeclined');
}

public function test_declined_reason_is_shown_on_form(): void
{
    $this->withAuthorizedUser();
    $po = PurchaseOrder::factory()->declined('Te duur')->create();

    Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getRouteKey()])
        ->assertFormFieldIsVisible('declined_reason')
        ->assertFormSet(['declined_reason' => 'Te duur']);
}

public function test_can_fill_notes_on_purchase_order(): void
{
    $this->withAuthorizedUser();
    $po = PurchaseOrder::factory()->open()->create();

    Livewire::test(EditPurchaseOrder::class, ['record' => $po->getRouteKey()])
        ->fillForm(['notes' => 'Gekocht voor het zeilkamp'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('purchase_orders', ['id' => $po->id, 'notes' => 'Gekocht voor het zeilkamp']);
}
```

Note: if `assertFormFieldDisabled` / `assertFormFieldIsVisible` are not available under the installed Filament testing API, use the equivalent `assertFormFieldIsDisabled` / `assertFormFieldVisible` helpers.

New feature test `tests/Feature/Filament/Admin/Resources/Members/PurchaseOrdersRelationManagerTest.php`:

```php
public function test_shows_only_purchase_orders_assigned_to_the_member(): void
{
    $this->withAuthorizedUser();
    $member = Member::factory()->createQuietly();
    $assigned = PurchaseOrder::factory()->forMember($member)->createQuietly();
    PurchaseOrder::factory()->createQuietly();

    Livewire::test(PurchaseOrdersRelationManager::class, [
        'ownerRecord' => $member->getRouteKey(),
        'pageClass' => ViewMember::class,
    ])
        ->assertCanSeeTableRecords([$assigned]);
}

public function test_create_action_assigns_member_and_fills_creditor_data(): void
{
    $this->withAuthorizedUser();
    $member = Member::factory()->has(PaymentInformation::factory()->state([
        'banking_account_number' => 'NL02ABNA0123456789',
    ]))->createQuietly();
    $costCenter = CostCenter::factory()->create();

    Livewire::test(PurchaseOrdersRelationManager::class, [
        'ownerRecord' => $member->getRouteKey(),
        'pageClass' => ViewMember::class,
    ])
        ->callAction('create', data: [
            'description' => 'Vlaggetjes voor de haven',
            'date' => '2026-09-23',
            'lines' => [
                ['description' => 'Vlaggetjes', 'price' => 25, 'price_vat' => 5.25, 'cost_center_id' => $costCenter->id],
            ],
        ]);

    $this->assertDatabaseHas('purchase_orders', [
        'member_id' => $member->id,
        'status' => PurchaseOrderStatus::Open->value,
        'creditor_iban' => 'NL02ABNA0123456789',
    ]);
}
```

Model/feature additions: add a relation assertion to `tests/Feature/Models/PurchaseOrderTest.php` (`testPurchaseOrderBelongsToAssignedMember` / `$member->purchaseOrders` contains the assigned order).

## Context docs update

Update the existing docs (no new files) so the domain model stays accurate:

- `.agents/docs/purchase-orders/CONTEXT.md` — PurchaseOrder entry gains: "optionally assigned to a Member (the member's name and IBAN are copied into the creditor fields and locked), free-text `notes`, and a `declined_reason` recorded when declined manually"; PurchaseOrderStatus entry: "Declined (manually with a required reason, or automatically on a SEPA reversal)".
- `.agents/docs/members/CONTEXT.md` — Member entry gains: "can be assigned PurchaseOrders (reimbursable expenses), visible as a read-only relation with a create action".

## Risks and open points

- `Repeater::make('lines')->relationship()` inside the relation-manager `CreateAction` modal: relationship saving from an action form is supported but is the riskiest part of this plan. Prove it in `test_create_action_assigns_member_and_fills_creditor_data`. Fallback described above (redirect to the create page with a `member_id` query parameter).
- The member `Select` uses an options closure loading all members (matching `HouseholdMemberActions` and `AttachPurchaseOrderAction`). `Member::name` is an accessor, so `Select::relationship('member', 'name')` with `->searchable()` would generate SQL against a non-column and must not be used.
- `PurchaseOrderStatusLabels::options()` currently omits `Declined`; without the addition the status select shows the raw value for declined orders.
- Decline is deliberately restricted to `Open` (per requirement); the reversal path in `BankTransactionServiceImpl::linkReversal()` declines from `Pending` without a reason and `unlinkReversal()` restores `Pending` and clears `declined_reason` via the changed `markAsPending`.
