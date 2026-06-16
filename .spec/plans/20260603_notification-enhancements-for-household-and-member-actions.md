# Implementation Plan: Notification Enhancements (Dutch translations)

Goal
----
Add UI notifications for important member/household actions so admins get immediate feedback in the Filament UI. All translations will be added to `lang/nl/notifications.php` (Dutch only) as requested.

Scope
-----
Target actions across Member and Household flows where a notification improves clarity:

- Member create / update / delete
- Household create / delete
- Member added to / removed from household
- Membership change (billing applied)
- Household billing recalculation completed
- Invoice creation
- Billable item instance creation/removal
- Activity attach/detach to a member
- Volunteer flag changes

Proposed translation keys (Dutch)
---------------------------------
Add the following keys to `lang/nl/notifications.php` and their Dutch messages:

- `member_created` => "Lid succesvol aangemaakt"
- `member_updated` => "Lid succesvol bijgewerkt"
- `member_deleted` => "Lid succesvol verwijderd"
- `household_created` => "Huishouden succesvol aangemaakt" (already added)
- `household_deleted` => "Huishouden succesvol verwijderd"
- `member_added_to_household` => "Lid succesvol toegevoegd aan huishouden" (already added)
- `member_removed_from_household` => "Lid succesvol verwijderd uit huishouden" (already added)
- `membership_changed_billing_applied` => "Lidmaatschap gewijzigd; facturering bijgewerkt"
- `household_billing_recalculated` => "Facturering voor huishouden opnieuw berekend"
- `invoice_created` => "Factuur succesvol aangemaakt"
- `billable_item_instance_created` => "Factuurregel succesvol toegevoegd"
- `billable_item_instance_removed` => "Factuurregel succesvol verwijderd"
- `activity_attached` => "Activiteit gekoppeld aan lid"
- `activity_detached` => "Activiteit verwijderd van lid"
- `member_volunteer_updated` => "Vrijwilligersstatus bijgewerkt"

Where to show notifications and implementation notes
-------------------------------------------------
Below each item I list the suggested file(s) to update and the code pattern to add.

1. Member created
   - Files: Filament MemberResource Create handler (only) — do NOT add notifications in Domain or Infrastructure code
   - Action: after the member is created in the Filament create handler, call:
     - Notification::make()->success()->title(__('notifications.member_created'))->send();

2. Member updated
   - Files: Filament MemberResource save handler (only)
   - Action: on successful update via the Filament form, show __('notifications.member_updated')
   - Note: Do not add notifications in the observer or other domain/infrastructure code. Filament already shows a generic saved toast; add domain-specific toasts only when updates are performed through the Filament UI.

3. Member deleted (soft delete)
   - Files: Filament MemberResource delete handler (only)
   - Action: after deletion via Filament, Notification::make()->success()->title(__('notifications.member_deleted'))->send();

4. Household created
   - Files: Filament Relation Managers / MemberResource (already implemented in Members relation manager)
   - Action: already added from Filament code. Keep as-is.

5. Household deleted
   - Files: Filament HouseholdResource delete handler (only)
   - Action: after deletion via Filament, Notification::make()->success()->title(__('notifications.household_deleted'))->send();

6. Member added to / removed from household
   - Files: Members relation manager (add_member action) and Households relation manager (add_member/remove action) — only Filament handlers
   - Action: already implemented in Filament relation managers. Ensure both resources use the same translation keys (`member_added_to_household`, `member_removed_from_household`).

7. Membership change (billing applied)
   - Files: Filament MemberResource save handler (only). Do NOT add notifications inside MemberObserver or billing applicator implementations.
   - Action: after the Filament form changes membership and the save completes, show Notification::make()->success()->title(__('notifications.membership_changed_billing_applied'))->send();
   - Notes: Only show this when the change originated from the Filament UI to avoid noisy notifications from background processes.

8. Household billing recalculation completed
   - Files: Filament action handlers (if the recalculation is triggered directly from Filament). Do NOT place notifications inside ApplySameHouseholdBilling implementation or in observers.
   - Action: If a Filament action triggers recalculation synchronously, show Notification::make()->info()->title(__('notifications.household_billing_recalculated'))->send() when it completes.
   - Notes: If recalculation runs asynchronously (queue/job), prefer logging or targeted in-app notifications to a user rather than a global Filament toast.

9. Invoice created
   - Files: Filament InvoiceResource create handler (only)
   - Action: after creating an invoice through the Filament UI, show Notification::make()->success()->title(__('notifications.invoice_created'))->send();

10. Billable item instance created / removed
    - Files: BillableItemInstancesRelationManager create/remove handlers (Filament only)
    - Action: after the Filament handler creates or removes the item, show Notification::make()->success()->title(__('notifications.billable_item_instance_created'))->send(); (and similar for removal)

11. Activity attached / detached to a member
    - Files: ActivitiesRelationManager (attach/detach actions) — Filament only
    - Action: after the Filament attach/detach action completes, show Notification::make()->success()->title(__('notifications.activity_attached'))->send(); and 'activity_detached'.

12. Volunteer flag changes
    - Files: Filament MemberResource save handler (only)
    - Action: when the volunteer flag is changed through the Filament form and the save completes, show Notification::make()->success()->title(__('notifications.member_volunteer_updated'))->send();

Implementation steps
--------------------
1. Add translation keys to `lang/nl/notifications.php` (the file already contains the three household keys). Append the proposed keys and translations.

2. Add Notification calls to the Filament resource handlers and relation managers only. Use Filament's Notification façade for consistency:

```php
use Filament\Notifications\Notification;

Notification::make()
    ->success()
    ->title(__('notifications.member_created'))
    ->send();
```

3. Enforce the rule: Notifications MUST only be created from Filament code (resource handlers, pages, relation managers). Do NOT add Notification calls in Domain or Infrastructure layers (observers, repositories, applicators, domain services). This keeps UI concerns out of the domain and avoids noisy notifications from background processes.

Priority (recommended)
----------------------
1. High priority (deliver first):
   - Member created/updated/deleted messages
   - Member added/removed from household (already implemented)
   - Household created / deleted
   - Invoice created

2. Medium priority:
   - Membership changed billing applied (only on UI-initiated change)
   - Member volunteer status change

3. Low priority:
   - Household billing recalculated notification (consider logging / background job instead)
   - Billable item instance messages
   - Activity attach/detach notifications

Notes and caveats
-----------------
- Keep notification volume low: prefer domain-relevant success notifications (creation, deletion, membership change) and avoid repeated background notifications that will clutter the UI.
- Use different severity levels: `success` for user-initiated successful actions, `info` for background processing results, `danger` for failures.
- If billing recalculation becomes asynchronous, consider using a queue and a targeted notification mechanism (e.g., email or in-app notification targeted to the user who performed the action) rather than a generic Filament toast.

Deliverables
------------
- Updated `lang/nl/notifications.php` with all proposed keys.
- A list of modified files (resource handlers and observers) with Notification calls added.
- Focused feature tests for high-priority changes.
