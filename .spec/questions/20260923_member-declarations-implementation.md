# Member declarations (declaraties) — implementation suggestions

Date: 2026-09-23

## Summary (most recommended first)

1. **Build a dedicated `Declaration` + `DeclarationLine` domain, cloned from the PurchaseOrder blueprint** — `declarations` (with `member_id` instead of creditor fields, `image_path`, `status`) and `declaration_lines` (`description`, `price`, `price_vat`, `cost_center_id`). This mirrors `purchase_orders`/`purchase_order_lines` exactly (same `decimal(10,3)` money columns, `CompoundPrice` totals, header+lines Repeater form). It is greenfield — no declaration code exists anywhere in the codebase today.
2. **Reuse the `PurchaseOrderStatus` workflow verbatim**: `Open → Pending → Paid`, plus `Declined`. It maps perfectly onto the future member flow too: Open = member-editable/submitted, Pending = approved awaiting payment, Paid = reimbursed. This means no status redesign later.
3. **Get bank matching + bookkeeping almost for free**: `bank_transaction_references` is already polymorphic (`nullableMorphs('reference')`), so `Declaration::bankTransactions()` works with zero schema change and "Paid" can be verified against a real bank debit. Match on the member's IBAN from `payment_information` instead of a creditor IBAN.
4. **Admin overview**: `DeclarationResource` with status tabs (`all/open/pending/paid`) + member column + totals, a book-year filter copied from `PurchaseOrdersTable`, and a small `DeclarationStatsOverview` widget ("X open / Y paid / open amount") — status is visible at a glance without clicking.
5. **MemberResource integration**: `DeclarationsRelationManager` modeled on `InvoicesRelationManager` (`relatedResource` + `ViewOrEdit::route()`), plus `CreateAction` so admins can file a declaration on a member's behalf.
6. **Future member self-service**: keep all logic in `app/Domain/Declarations` (pure domain, enforced by `dev/phpstan/Rules/DomainDependencyRule.php`) so the later member surface — a second Filament panel or an extension of the public web routes — only needs UI. The `members.user_id` seam is already there.

**Alternative (not recommended)**: generalize `PurchaseOrder` into a polymorphic "expense document" with a `payable`/`creditor` morph (member vs. supplier) and a `type` discriminator. It avoids duplicated tables but requires touching the stable banking-matching and bookkeeping code paths for POs (`BankTransactionServiceImpl`, `BookkeepingRecordDbRepository::createForPurchaseOrder`) and couples member concerns into the Purchase Orders context. Only worth it if a third payee type appears.

---

## 1. Data model (Solution 1, expanded)

Blueprint tables: `database/migrations/2026_06_26_203536_create_purchase_orders_table.php`, `..._203537_create_purchase_order_lines_table.php`.

Suggested schema:

- `declarations`: `id`, `member_id` (FK → members, required, indexed), `description` (string, `recordTitleAttribute`), `date` (date), `status` (string, indexed, default `open`, cast to `DeclarationStatus`), `image_path` (nullable string), timestamps. Optionally `notes` (nullable text, useful for admin↔member communication and later the member flow).
- `declaration_lines`: `id`, `declaration_id` (FK, `cascadeOnDelete`), `description`, `price` decimal(10,3), `price_vat` decimal(10,3) (VAT **amount**, auto-filled at 21% exactly like `PurchaseOrderForm` does at `Schemas/PurchaseOrderForm.php:82-92`), `cost_center_id` (FK → cost_centers, required, indexed), timestamps.

Copy these existing pieces nearly verbatim:

- `app/Models/PurchaseOrder.php:33-75` — `lines()` HasMany, `total(): Attribute` reducing lines to `CompoundPrice` (`app/Domain/Invoices/CompoundPrice.php`), `displayName()`, `openOrPending()` scope.
- `app/Models/PurchaseOrderLine.php:41-50` — `compoundPrice` attribute.
- Money convention: decimal columns + `'price' => 'decimal:3'` casts (not integer cents) — see `.agents/docs/GLOSSARY.md` (CompoundPrice shared kernel).
- Cost centers are shared kernel; reuse `CostCenter` as-is (`CostCenter::query()->orderBy('number')->pluck('title', 'id')` for the select, as in `PurchaseOrderForm.php:100`).
- Add `declarations(): HasMany` to `app/Models/Member.php` (next to `invoices()` at line 36) and `declarationLines(): HasMany` to `app/Models/CostCenter.php` (next to `purchaseOrderLines()`).

Domain layer (mirrors `app/Domain/PurchaseOrders/`): `DeclarationId extends NumericId`, `DeclarationIdList`, `DeclarationStatus` (string enum `Open/Pending/Paid/Declined`), `DeclarationService` + `DeclarationServiceImpl`, `DeclarationRepository` interface; implementation in `app/Infrastructure/Declarations/DeclarationRepositoryDb` (JeroenG\Autowire). Naming: `Declaration` (per `.agents/docs/GLOSSARY.md` style — English domain names; avoid "expense claim / reimbursement / onkosten"); Dutch only in `lang/nl/labels.php` (`declaratie`, `declaraties`).

## 2. Status & payment workflow (Solution 2, expanded)

`app/Domain/PurchaseOrders/PurchaseOrderStatus.php:7-13` semantics carry over unchanged:

| Status | Meaning for declarations |
|---|---|
| Open | Editable (later: member can still edit), awaiting review |
| Pending | Approved, awaiting reimbursement |
| Paid | Reimbursed (ideally matched to a bank transaction) |
| Declined | Rejected by admin |

Transition UI: clone `app/Filament/Admin/Resources/PurchaseOrders/Actions/PurchaseOrderStateActions.php` (`markAsPending` / `markAsPaid` / `markAsDeclined`, `requiresConfirmation`, `dispatch('markedAs...')` + `#[On(...)] refresh()` in the view page). Policy guard: clone `app/Policies/PurchaseOrderPolicy.php:22-34` — update/delete only while `status === Open`; status field `->disabled()` in the form.

**Bookkeeping**: extend `app/Infrastructure/Bookkeeping/BookkeepingRecordDbRepository` with a `createForDeclaration` modeled on `createForPurchaseOrder` (lines 66-103): one record per cost center, guarded by `whereNotExists` on the polymorphic reference, created on the Pending/Paid transition. Sign is an open question (see decision points) — POs write `SUM(-price)` since money leaves the club; a reimbursement to a member is also money leaving the club, so most likely negative too.

**Bank matching (big win)**: `bank_transaction_references` (`2026_07_05_075109`) already has `nullableMorphs('reference')` + uniqueness constraint, so `Declaration::bankTransactions(): MorphToMany` plugs into `BankTransaction::matched_amount`/`unmatched_amount` and `BankTransactionServiceImpl::complete()` for free. For auto-matching, extend `PurchaseOrderRepositoryDb::findMatchingDebit` logic with a declaration variant that matches the member's IBAN from `payment_information` instead of `creditor_iban`, amount = line sum, date ±30 days.

## 3. Admin UX (Solution 4, expanded)

- `app/Filament/Admin/Resources/Declarations/DeclarationResource.php`, structured like `Resources/PurchaseOrders/` (`Tables/`, `Schemas/`, `Actions/`, `Pages/`, `RelationManagers/`, `Widgets/` — the split is enforced by convention and custom PHPStan rules in `dev/phpstan/Rules/`).
- **List**: tabs `all/open/pending/paid` (`ListPurchaseOrders.php:22-37`), columns: `image_path` (`ImageColumn`, `->disk('local')`, toggleable), **member name** (searchable/sortable via `member.name`), `description`, `date`, `status` (via `DeclarationStatusLabels` + `lang/nl/labels.php`), `total` (CompoundPrice), book-year `SelectFilter` with `FiltersLayout::BeforeContent` (`Tables/PurchaseOrdersTable.php`). Row click via `ViewOrEdit::route()` (`app/Filament/Admin/Utils/ViewOrEdit.php`).
- **"Really easy to see status"** — two cheap layers:
  1. `Widgets/DeclarationStatsOverview.php` on the dashboard (pattern: `app/Filament/Admin/Widgets/Dashboard/MemberOverview.php`, `Resources/InvoiceBatches/Widgets/BatchStatsOverview.php`): "open count + open total", "pending", "paid this year". Optionally `->badge()` on the nav item with the open count.
  2. Status tabs + colored status column in the list (as PO does).
- **Image**: single `image_path` column + `FileUpload::make('image_path')->image()->directory('declarations')->disk('local')->visibility('private')->previewable()->preventFilePathTampering()` (the tampering guard is new guidance in Filament v5 and fits, since member-uploaded paths must not be resolvable to other records). Cleanup via `DeclarationObserver::deleted()` like `app/Observers/PurchaseOrderObserver.php:12-17`.
- **Permissions**: add `view_any_declarations … delete_any_declarations` to `app/Domain/Authorization/ResourcePermission.php` (pattern at lines 159-164) — `RolePermissionSeeder::allPermissionsFor('declarations')` then works automatically; give `financial_administration` full and `member_administration` view+create. `DeclarationPolicy extends ResourcePolicy` (`app/Policies/ResourcePolicy.php`).
- **Navigation placement** (decision point): `NavigationGroup::MemberAdministration` + `MembersCluster` (declaraties are member-centric; admins processing them live in member administration) vs. `NavigationGroup::Invoicing` + `InvoicingCluster` next to PurchaseOrders (money outflows together; the Invoicing cluster gate already matches the role that pays). Note the custom PHPStan rule requires `public static $navigationGroup` referencing the `NavigationGroup` enum (`dev/phpstan/Rules/FilamentResourceNavigationGroupRule.php`).

## 4. MemberResource integration (Solution 5, expanded)

`DeclarationsRelationManager` in `app/Filament/Admin/Resources/Members/RelationManagers/`, modeled on `InvoicesRelationManager.php:24-94`:

- `protected static string $relationship = 'declarations';`
- `protected static ?string $relatedResource = DeclarationResource::class;`
- `->recordUrl(ViewOrEdit::route(DeclarationResource::class))` so rows open the full declaration (image + lines).
- Header `CreateAction` (pre-filling `member_id` automatically via the relation) for filing on a member's behalf — same as `InvoicesRelationManager`'s `CreateAction::make()`.
- Register in `MemberResource::getRelations()` (currently `app/Filament/Admin/Resources/Members/MemberResource.php:64-75`, after `InvoicesRelationManager`).
- `ViewMember`/`EditMember` already use `hasCombinedRelationManagerTabsWithContent()`, so declarations appear as a tab for free.

Optionally the inverse: `MemberRelationManager` on `DeclarationResource` is unnecessary — the member column + filter suffices.

## 5. Later: member self-service

Two options when you get there:

- **Second Filament panel** (`app/Providers/Filament/MemberPanelProvider.php`, `php artisan make:filament-panel member`) — recommended if members also get invoices/objects/rentals later. Filament supports per-panel `canAccessPanel(Panel $panel)` (already implemented on `app/Models/User.php:50-53`, just branch on `$panel->getId()`), and each panel has its own resources, so the member panel gets a scoped `MyDeclarations` resource limited to `auth()->user()->member` (`User::member(): HasOne`, `app/Models/User.php:55-58`). Per-panel permission scoping is a standard Filament multi-panel setup.
- **Extend the public web routes** (Blade, like the registration wizard in `routes/web.php:8-23`) — lighter, consistent with the only existing non-admin surface, but you hand-roll forms/uploads.

Either way, the design work to do *now* is minimal: (a) put create/update rules in `DeclarationService` rather than Filament pages so both surfaces share them; (b) keep the 4-status enum so member-created declarations are "submitted as Open" and the admin approves to Pending; (c) validate `member_id` server-side from the authenticated user in the member surface (never from form input).

## 6. Tests (per repo convention)

- `database/factories/DeclarationFactory.php` with `open()/pending()/paid()/declined()` + `withLines(int $count)` (see `PurchaseOrderFactory.php:28-48`), `DeclarationLineFactory` with `price_vat = price * 0.21` (`PurchaseOrderLineFactory.php:23`).
- `tests/Feature/Models/DeclarationTest.php` (scopes), `tests/Feature/Filament/Admin/Resources/DeclarationResourceTest.php` (create with lines, state actions + bookkeeping side effects, cannot edit when not Open), `tests/Feature/Observers/DeclarationObserverTest.php` (`Storage::fake('local')`), `tests/Unit/Domain/Declarations/DeclarationServiceImplTest.php` + `DeclarationRepositoryExpectation` mock helper. PHPUnit only (Pest is prohibited), run via `./Taskfile artisan test --compact --filter=...`. Note the custom PHPStan collectors in `dev/phpstan/Collectors/` enforce test coverage per source file.
- Also update `.agents/docs/` (new `declarations/CONTEXT.md`, GLOSSARY entry with an *Avoid* list, CONTEXT-MAP row) — the agent-docs skill governs this.

## Open decision points

1. **Bookkeeping sign & timing** — write negative (expense) `BookkeepingRecord`s per cost center on Pending like POs? And should VAT be split for BTW-aftrek? (`createForPurchaseOrder` already splits `price`/`price_vat`.)
2. **One image or many** — user said "an image" (singular); a `DeclarationImage` morph/hasMany is a cheap later upgrade if receipts per line become necessary. Start with `image_path`.
3. **Navigation group** — MemberAdministration/MembersCluster vs Invoicing/InvoicingCluster (see §3).
4. **Declined in v1?** POs have it mostly for bank stornos; for declarations a "declined/rejected" is arguably more important (admin rejects a claim) — recommend including it.
5. **Who may create** — only financial admin, or also member administration filing on behalf of members?

## Sources

Codebase (primary):

- `.agents/docs/CONTEXT-MAP.md`, `.agents/docs/GLOSSARY.md`, `.agents/docs/purchase-orders/CONTEXT.md`, `.agents/docs/members/CONTEXT.md`
- `database/migrations/2026_06_26_203536_create_purchase_orders_table.php`, `2026_06_26_203537_create_purchase_order_lines_table.php`, `2026_07_05_075109_create_banking_transaction_references_table.php`
- `app/Models/PurchaseOrder.php`, `app/Models/PurchaseOrderLine.php`, `app/Models/Member.php`, `app/Models/User.php`
- `app/Domain/PurchaseOrders/*`, `app/Domain/Invoices/CompoundPrice.php`, `app/Domain/BankTransactions/BankTransactionServiceImpl.php`
- `app/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDb.php`, `app/Infrastructure/Bookkeeping/BookkeepingRecordDbRepository.php`
- `app/Filament/Admin/Resources/PurchaseOrders/**` (esp. `Schemas/PurchaseOrderForm.php`, `Tables/PurchaseOrdersTable.php`, `Actions/PurchaseOrderStateActions.php`), `app/Filament/Admin/Resources/Members/MemberResource.php`, `app/Filament/Admin/Resources/Members/RelationManagers/InvoicesRelationManager.php`, `app/Filament/Admin/Utils/ViewOrEdit.php`
- `app/Policies/PurchaseOrderPolicy.php`, `app/Policies/ResourcePolicy.php`, `app/Domain/Authorization/ResourcePermission.php`, `database/seeders/RolePermissionSeeder.php`
- `dev/phpstan/Rules/DomainDependencyRule.php`, `dev/phpstan/Rules/FilamentResourceNavigationGroupRule.php`

Web:

- Filament v5 — Panel configuration (multiple panels): https://filamentphp.com/docs/5.x/panel-configuration
- Filament v5 — Managing relationships (relation managers): https://filamentphp.com/docs/5.x/resources/managing-relationships
- Filament v5 — File upload (private disks, `preventFilePathTampering()`): https://filamentphp.com/docs/5.x/forms/file-upload
- Filament v5 — Users & panel authorization (`canAccessPanel(Panel $panel)`): https://filamentphp.com/docs/5.x/users/overview
- Filament v5 — Nested resources (alternative to relation managers): https://filamentphp.com/docs/5.x/resources/nesting
