# Implementation Plan: Household Management from MemberResource

## Overview

Convert the Member-Household relationship from a many-to-many (with unique constraint) to a true one-to-many relationship (Member belongs to Household) and manage households purely from the MemberResource in Filament.

**Current State:**
- Many-to-many relationship via `household_member` pivot table
- Unique constraint on `member_id` effectively restricts to one household
- HouseholdResource manages the relationship via AttachAction/DetachAction
- Domain layer already expects single `householdId`
- Repository takes first household only

**Desired State:**
- True one-to-many: `members.household_id` foreign key
- All household management happens in MemberResource
- Can view all household members from a member's page
- Can create new households when adding a member
- Can join existing households
- Simpler queries, better performance, clearer intent

---

## Phase 1: Database Migration

### 1.1 Create Migration to Convert Relationship

**File:** `database/migrations/YYYY_MM_DD_HHMMSS_convert_households_to_one_to_many.php`

**Actions:**
1. Add `household_id` column to `members` table (nullable, foreign key)
2. Migrate existing data from `household_member` pivot table to `members.household_id`
3. Drop the `household_member` pivot table
4. Add foreign key constraint with appropriate cascade/null behavior

**Migration Steps:**
```php
// Up
Schema::table('members', function (Blueprint $table) {
    $table->foreignId('household_id')
        ->nullable()
        ->after('membership_id')
        ->constrained('households')
        ->nullOnDelete(); // When household is deleted, set member's household_id to null
});

// Migrate existing data
DB::table('household_member')->orderBy('household_id')->chunk(100, function ($rows) {
    foreach ($rows as $row) {
        DB::table('members')
            ->where('id', $row->member_id)
            ->update(['household_id' => $row->household_id]);
    }
});

// Drop pivot table
Schema::dropIfExists('household_member');

// Down
Schema::create('household_member', function (Blueprint $table) {
    $table->foreignId('household_id')->constrained()->cascadeOnDelete();
    $table->foreignId('member_id')->constrained()->cascadeOnDelete();
    $table->primary(['household_id', 'member_id']);
    $table->unique('member_id'); // Restore unique constraint
    $table->timestamps();
});

// Migrate data back
DB::table('members')->whereNotNull('household_id')->chunk(100, function ($rows) {
    $inserts = [];
    foreach ($rows as $row) {
        $inserts[] = [
            'household_id' => $row->household_id,
            'member_id' => $row->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    DB::table('household_member')->insert($inserts);
});

Schema::table('members', function (Blueprint $table) {
    $table->dropForeign(['household_id']);
    $table->dropColumn('household_id');
});
```

**Data Validation:**
- Before migration, verify no member is in multiple households (shouldn't be possible due to unique constraint)
- Add logging to track migration progress
- Consider adding a data verification step after migration

---

## Phase 2: Model Updates

### 2.1 Update Member Model

**File:** `app/Models/Member.php`

**Changes:**

1. **Replace `households()` with `household()`:**
```php
// Remove this:
public function households(): BelongsToMany
{
    return $this->belongsToMany(Household::class, HouseholdMember::class);
}

// Add this:
public function household(): BelongsTo
{
    return $this->belongsTo(Household::class);
}
```

2. **Add `householdMembers()` relation:**
```php
/**
 * Get all members in the same household as this member.
 * Returns an empty collection if member is not in a household.
 */
public function householdMembers(): HasMany|Builder
{
    if ($this->household_id === null) {
        return $this->hasMany(self::class, 'household_id', 'id')->whereRaw('1 = 0'); // Empty relation
    }
    
    return $this->hasMany(self::class, 'household_id', 'household_id')
        ->where('id', '!=', $this->id); // Exclude self
}
```

3. **Add `household_id` to `$fillable` or remove guarding:**
```php
protected $fillable = [
    // ... existing fields ...
    'household_id',
];
```

4. **Update casts if needed** (household_id is already bigint, no cast needed)

### 2.2 Update Household Model

**File:** `app/Models/Household.php`

**Changes:**

1. **Replace `members()` BelongsToMany with HasMany:**
```php
// Remove this:
public function members(): BelongsToMany
{
    return $this->belongsToMany(Member::class, HouseholdMember::class);
}

// Add this:
public function members(): HasMany
{
    return $this->hasMany(Member::class);
}
```

2. **Add helper methods:**
```php
/**
 * Get the count of members in this household.
 */
public function getMemberCountAttribute(): int
{
    return $this->members()->count();
}

/**
 * Get formatted list of member names.
 */
public function getMemberNamesAttribute(): string
{
    return $this->members->map(fn (Member $member) => $member->name)->join(', ');
}
```

### 2.3 Remove Pivot Model

**Files to Delete:**
- `app/Models/Pivots/HouseholdMember.php` (no longer needed)

**Files to Update:**
- Any imports of `HouseholdMember` should be removed

---

## Phase 3: Observer Updates

### 3.1 Remove HouseholdMemberObserver

**File to Delete:**
- `app/Observers/HouseholdMemberObserver.php`

**Reason:** No longer have a pivot model to observe.

### 3.2 Update MemberObserver

**File:** `app/Observers/MemberObserver.php`

**Changes:**

1. **Add `updated()` method to watch for household changes:**
```php
public function updated(Member $member): void
{
    // Check if household_id changed
    if ($member->isDirty('household_id')) {
        $this->handleHouseholdChange($member);
    }
}

private function handleHouseholdChange(Member $member): void
{
    // Get both old and new household IDs
    $oldHouseholdId = $member->getOriginal('household_id');
    $newHouseholdId = $member->household_id;
    
    // Trigger billing recalculation for the old household (if any)
    if ($oldHouseholdId !== null) {
        $this->recalculateBillingForHousehold($oldHouseholdId);
    }
    
    // Trigger billing recalculation for the new household (if any)
    if ($newHouseholdId !== null) {
        $this->recalculateBillingForHousehold($newHouseholdId);
    }
}

private function recalculateBillingForHousehold(int $householdId): void
{
    // Apply same household billing discounts
    // This is what HouseholdMemberObserver was doing
    ApplySameHouseholdBilling::apply();
    // Or more targeted: recalculate only for members in this household
}
```

**Note:** Review `ApplySameHouseholdBilling::apply()` to understand if it needs household-specific triggering or if it recalculates globally.

---

## Phase 4: Filament Resource Updates

### 4.1 Update MemberResource Form

**File:** `app/Filament/Admin/Resources/Members/Schemas/MemberForm.php`

**Changes:**

1. **Add household field to "Membership Information" tab:**
```php
Tabs\Tab::make(__('labels.membership_information'))
    ->schema([
        Forms\Components\Select::make('membership_id')
            ->relationship('membership', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->label(__('labels.membership')),
        
        // ADD THIS:
        Forms\Components\Select::make('household_id')
            ->relationship('household', 'id') // Household has no name field
            ->label(__('labels.household'))
            ->searchable()
            ->preload()
            ->createOptionForm([
                // Empty form - households have no fields
                // Or add hidden field to make it valid
                Forms\Components\Hidden::make('id'),
            ])
            ->createOptionModalHeading(__('labels.create_household'))
            ->getOptionLabelFromRecordUsing(function (Household $record): string {
                // Display household by member names
                return $record->member_names ?: __('labels.household_no_members');
            })
            ->searchable()
            ->getSearchResultsUsing(function (string $search): array {
                return Household::whereHas('members', function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('infix_name', 'like', "%{$search}%");
                })
                ->with('members')
                ->limit(50)
                ->get()
                ->mapWithKeys(function (Household $household) {
                    return [$household->id => $household->member_names];
                })
                ->toArray();
            })
            ->helperText(__('labels.household_helper_text'))
            ->live()
            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                // Could show preview of household members here
            }),
        
        Forms\Components\Toggle::make('is_volunteer')
            ->label(__('labels.is_volunteer'))
            ->default(false),
    ]),
```

### 4.2 Update MembersTable (Optional)

**File:** `app/Filament/Admin/Resources/Members/Tables/MembersTable.php`

**Add household column:**
```php
Tables\Columns\TextColumn::make('household.member_names')
    ->label(__('labels.household_members'))
    ->wrap()
    ->toggleable(),
```

### 4.3 Create HouseholdMembersRelationManager for MemberResource

**File:** `app/Filament/Admin/Resources/Members/RelationManagers/HouseholdMembersRelationManager.php`

This is the key piece! This shows all members in the same household.

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\RelationManagers;

use App\Enums\NavigationGroup;
use App\Models\Household;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class HouseholdMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'householdMembers';

    protected static ?string $title = 'Household Members';
    
    protected static ?string $icon = 'heroicon-o-user-group';

    public function form(Form $form): Form
    {
        // No form needed - we don't edit members here
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('labels.name'))
                    ->searchable(['first_name', 'last_name', 'infix_name'])
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('last_name', $direction)
                            ->orderBy('first_name', $direction);
                    })
                    ->url(fn (Member $record): string => 
                        route('filament.admin.resources.members.edit', ['record' => $record])
                    ),
                
                Tables\Columns\TextColumn::make('membership.name')
                    ->label(__('labels.membership'))
                    ->badge()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('age')
                    ->label(__('labels.age'))
                    ->sortable(),
                
                Tables\Columns\IconColumn::make('is_volunteer')
                    ->label(__('labels.volunteer'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('membership')
                    ->relationship('membership', 'name'),
            ])
            ->headerActions([
                // Action to add existing members to this household
                Tables\Actions\Action::make('add_member')
                    ->label(__('labels.add_member_to_household'))
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (): bool => $this->getOwnerRecord()->household_id !== null)
                    ->form([
                        Forms\Components\Select::make('member_id')
                            ->label(__('labels.member'))
                            ->options(function (): array {
                                // Get members not in any household or in different household
                                return Member::whereNull('household_id')
                                    ->orWhere('household_id', '!=', $this->getOwnerRecord()->household_id)
                                    ->get()
                                    ->mapWithKeys(fn (Member $m) => [$m->id => $m->name])
                                    ->toArray();
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $member = Member::findOrFail($data['member_id']);
                        $member->update(['household_id' => $this->getOwnerRecord()->household_id]);
                        
                        Notification::make()
                            ->success()
                            ->title(__('notifications.member_added_to_household'))
                            ->send();
                    }),
                
                // Action to create a household if member doesn't have one
                Tables\Actions\Action::make('create_household')
                    ->label(__('labels.create_household'))
                    ->icon('heroicon-o-home')
                    ->visible(fn (): bool => $this->getOwnerRecord()->household_id === null)
                    ->requiresConfirmation()
                    ->modalHeading(__('labels.create_household_for_member'))
                    ->modalDescription(__('labels.create_household_description'))
                    ->action(function (): void {
                        $household = Household::create();
                        $this->getOwnerRecord()->update(['household_id' => $household->id]);
                        
                        Notification::make()
                            ->success()
                            ->title(__('notifications.household_created'))
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('remove_from_household')
                    ->label(__('labels.remove_from_household'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Member $record): void {
                        $record->update(['household_id' => null]);
                        
                        Notification::make()
                            ->success()
                            ->title(__('notifications.member_removed_from_household'))
                            ->send();
                    }),
                
                Tables\Actions\Action::make('view')
                    ->label(__('labels.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (Member $record): string => 
                        route('filament.admin.resources.members.edit', ['record' => $record])
                    ),
            ])
            ->emptyStateHeading(__('labels.no_household_members'))
            ->emptyStateDescription(__('labels.no_household_members_description'))
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateActions([
                Tables\Actions\Action::make('create_household')
                    ->label(__('labels.create_household'))
                    ->icon('heroicon-o-home')
                    ->action(function (): void {
                        $household = Household::create();
                        $this->getOwnerRecord()->update(['household_id' => $household->id]);
                        
                        Notification::make()
                            ->success()
                            ->title(__('notifications.household_created'))
                            ->send();
                    }),
            ]);
    }
    
    public function isReadOnly(): bool
    {
        // Make it read-only - we use custom actions instead of table edit
        return true;
    }
}
```

### 4.4 Register HouseholdMembersRelationManager in MemberResource

**File:** `app/Filament/Admin/Resources/Members/MemberResource.php`

**Update the `getRelations()` method:**
```php
public static function getRelations(): array
{
    return [
        RelationManagers\HouseholdMembersRelationManager::class, // ADD THIS
        RelationManagers\InvoicesRelationManager::class,
        RelationManagers\BillableItemInstancesRelationManager::class,
        RelationManagers\ActivitiesRelationManager::class,
        RelationManagers\MemberObjectsRelationManager::class,
    ];
}
```

**Remove/comment out the import if it exists:**
```php
// Remove this if it exists:
// use App\Filament\Admin\Resources\Members\RelationManagers\HouseholdMemberRelationManager;
```

---

## Phase 5: Update HouseholdResource (Optional)

Since household management is now in MemberResource, you can either:

**Option A: Keep HouseholdResource (Read-Only)**

Make it a view-only resource for seeing all households at a glance.

**File:** `app/Filament/Admin/Resources/Households/RelationManagers/HouseholdMembersRelationManager.php`

Update to use HasMany instead of BelongsToMany:

```php
public function table(Table $table): Table
{
    return $table
        ->columns([
            // ... existing columns ...
        ])
        ->headerActions([
            // Remove AttachAction
            // Add custom action to add members
            Tables\Actions\Action::make('add_member')
                ->form([
                    Forms\Components\Select::make('member_id')
                        ->label(__('labels.member'))
                        ->options(fn () => Member::whereNull('household_id')
                            ->get()
                            ->mapWithKeys(fn ($m) => [$m->id => $m->name])
                        )
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data, RelationManager $livewire): void {
                    $member = Member::find($data['member_id']);
                    $member->update(['household_id' => $livewire->getOwnerRecord()->id]);
                }),
        ])
        ->actions([
            // Replace DetachAction with remove action
            Tables\Actions\Action::make('remove')
                ->requiresConfirmation()
                ->action(fn (Member $record) => $record->update(['household_id' => null])),
        ]);
}
```

**Option B: Remove HouseholdResource**

If households are purely managed through members, consider removing the HouseholdResource entirely.

**Files to remove:**
- `app/Filament/Admin/Resources/Households/HouseholdResource.php`
- `app/Filament/Admin/Resources/Households/Table/HouseholdTable.php`
- `app/Filament/Admin/Resources/Households/RelationManagers/HouseholdMembersRelationManager.php`

**Navigation impact:** Households would no longer appear as a separate menu item.

**Recommendation:** Keep the resource for now in read-only mode to allow viewing all households. Can be removed later if not needed.

---

## Phase 6: Domain & Repository Updates

### 6.1 Update MemberDbRepository

**File:** `app/Infrastructure/Members/MemberDbRepository.php`

**Simplify household retrieval:**

```php
public function getById(MemberId $memberId): MemberEntity
{
    // Remove: $model = Member::with('households')->findOrFail($memberId->value);
    // Remove: $household = $model->households()->first();
    
    $model = Member::findOrFail($memberId->value);
    
    return new MemberEntity(
        id: MemberId::create($model->id),
        membershipId: MembershipId::create($model->membership_id),
        isVolunteer: $model->is_volunteer,
        householdId: $model->household_id ? HouseholdId::create($model->household_id) : null, // Direct access
        age: $model->age,
    );
}
```

**No domain changes needed** - the `Member` entity already expects a single `householdId`.

---

## Phase 7: Factory Updates

### 7.1 Update MemberFactory

**File:** `database/factories/MemberFactory.php`

**Update `inHousehold()` method:**

```php
// Replace this:
public function inHousehold(Household $household): self
{
    return $this->afterCreating(function (Member $member) use ($household) {
        $household->members()->syncWithoutDetaching([$member->id]);
    });
}

// With this:
public function inHousehold(Household $household): self
{
    return $this->state(fn (array $attributes) => [
        'household_id' => $household->id,
    ]);
}
```

**Or create a household automatically:**

```php
public function withHousehold(): self
{
    return $this->for(Household::factory(), 'household');
}
```

---

## Phase 8: Test Updates

### 8.1 Update MemberHouseholdMembersRelationTest

**File:** `tests/Feature/Models/MemberHouseholdMembersRelationTest.php`

**Current test expects `householdMembers()` relation - now it will work!**

Review and update:
- Change `$member->households()` to `$member->household()`
- Ensure `$member->householdMembers()` returns correct members
- Update assertions for one-to-many instead of many-to-many

```php
test('member can retrieve household members', function () {
    $household = Household::factory()->create();
    
    $owner = Member::factory()->inHousehold($household)->create();
    $spouse = Member::factory()->inHousehold($household)->create();
    $child = Member::factory()->inHousehold($household)->create();
    
    $householdMembers = $owner->householdMembers()->get();
    
    expect($householdMembers)->toHaveCount(2)
        ->and($householdMembers->contains($spouse))->toBeTrue()
        ->and($householdMembers->contains($child))->toBeTrue()
        ->and($householdMembers->contains($owner))->toBeFalse(); // Should not include self
});

test('member without household returns empty collection', function () {
    $member = Member::factory()->create(['household_id' => null]);
    
    expect($member->householdMembers()->get())->toBeEmpty();
});

test('member belongs to one household only', function () {
    $household = Household::factory()->create();
    $member = Member::factory()->inHousehold($household)->create();
    
    expect($member->household)->toBeInstanceOf(Household::class)
        ->and($member->household->id)->toBe($household->id);
});
```

### 8.2 Create Tests for New Functionality

**File:** `tests/Feature/Filament/Members/ManageHouseholdFromMemberResourceTest.php`

Create comprehensive tests for:
- Creating a household for a member
- Joining an existing household
- Viewing household members
- Adding members to household
- Removing members from household
- Changing household
- Billing recalculation on household changes

---

## Phase 9: Language Files

### 9.1 Add Missing Translation Keys

**Files:** `lang/*/labels.php`, `lang/*/notifications.php`

Add keys used in the Filament resources:

```php
// labels.php
'household' => 'Household',
'household_members' => 'Household Members',
'household_helper_text' => 'Select or create a household for this member',
'create_household' => 'Create Household',
'create_new_household' => 'Create New Household',
'join_existing_household' => 'Join Existing Household',
'household_no_members' => 'Empty Household',
'add_member_to_household' => 'Add Member to Household',
'remove_from_household' => 'Remove from Household',
'create_household_for_member' => 'Create Household for {name}',
'create_household_description' => 'This will create a new household and add this member to it.',
'no_household_members' => 'No Household Members',
'no_household_members_description' => 'This member is not in a household yet. Create one to see household members.',
'select_household' => 'Select Household',

// notifications.php
'household_created' => 'Household created successfully',
'member_added_to_household' => 'Member added to household',
'member_removed_from_household' => 'Member removed from household',
```

---

## Phase 10: Testing & Validation

### 10.1 Manual Testing Checklist

- [ ] Run migration successfully
- [ ] Verify existing household data migrated correctly
- [ ] Create a new member without household
- [ ] Create a new member with a new household
- [ ] Add a member to an existing household
- [ ] View household members from MemberResource
- [ ] Change a member's household
- [ ] Remove a member from household
- [ ] Delete a member (verify household_id nulled or member deleted)
- [ ] Delete a household (verify members' household_id set to null)
- [ ] Verify billing recalculation triggers correctly
- [ ] Test HouseholdResource still works (if kept)
- [ ] Verify all translations display correctly

### 10.2 Automated Testing

- [ ] Run full test suite: `php artisan test`
- [ ] Run specific test file: `php artisan test tests/Feature/Models/MemberHouseholdMembersRelationTest.php`
- [ ] Verify no N+1 queries in household member loading
- [ ] Test factories work correctly with new structure

### 10.3 Performance Testing

- [ ] Check query performance for household members
- [ ] Verify no extra queries when loading member with household
- [ ] Test with large datasets (100+ members, 30+ households)

---

## Phase 11: Cleanup & Documentation

### 11.1 Remove Old Code

- [ ] Delete `app/Models/Pivots/HouseholdMember.php`
- [ ] Delete `app/Observers/HouseholdMemberObserver.php`
- [ ] Remove unused imports
- [ ] Remove old relation manager if HouseholdResource is removed

### 11.2 Update Documentation

- [ ] Update README if household management is documented
- [ ] Add inline comments for complex logic
- [ ] Document the new household management workflow

### 11.3 Code Quality

- [ ] Run PHPStan: `vendor/bin/phpstan analyse`
- [ ] Run ECS: `vendor/bin/ecs check`
- [ ] Fix any coding standard violations
- [ ] Ensure all methods have proper return types

---

## Rollback Plan

If issues arise, rollback steps:

1. Revert migration: `php artisan migrate:rollback`
2. Restore pivot table and relationships
3. Revert model changes
4. Restore observers
5. Revert Filament resource changes

---

## Benefits After Implementation

1. **Simpler Data Model**
   - No pivot table
   - Direct foreign key relationship
   - Easier to understand and maintain

2. **Better Performance**
   - No join through pivot table
   - Simpler queries
   - Less database overhead

3. **Clearer Intent**
   - Code matches domain model (one household per member)
   - No confusion about many-to-many vs one-to-many

4. **Improved UX**
   - All household management in one place (MemberResource)
   - Easy to see and manage household members
   - Clear workflow for creating/joining households

5. **Better DX**
   - `$member->household` instead of `$member->households()->first()`
   - `$member->householdMembers()` works as expected
   - Matches existing domain layer expectations

---

## Implementation Order

**Recommended sequence:**

1. ✅ Phase 1: Database Migration (run on dev first)
2. ✅ Phase 2: Model Updates (breaks existing code, so do together with Phase 3)
3. ✅ Phase 3: Observer Updates
4. ✅ Phase 4: Filament Resource Updates (restore functionality)
5. ✅ Phase 6: Domain/Repository Updates (simplify code)
6. ✅ Phase 7: Factory Updates (fix tests)
7. ✅ Phase 8: Test Updates (make tests pass)
8. ✅ Phase 9: Language Files (polish UX)
9. ✅ Phase 10: Testing & Validation
10. ✅ Phase 11: Cleanup & Documentation

**Estimated Time:** 3-4 hours for implementation + testing

---

## Questions to Consider

1. **Should HouseholdResource be kept or removed?**
   - Recommendation: Keep as read-only for now, can remove later if not useful

2. **Should households have a "name" or "label" field?**
   - Current: No fields, just an ID container
   - Consideration: Adding a name would improve UX in selects
   - Example: "Smith Family", "Johnson Household"

3. **What happens to orphaned households (no members)?**
   - Should they be automatically deleted?
   - Add a scheduled job to clean up empty households?

4. **Household display in forms:**
   - Show as member names (current plan)
   - Add household name field?
   - Show address of primary member?

5. **Billing recalculation:**
   - Does `ApplySameHouseholdBilling::apply()` recalculate globally or per-household?
   - Should we optimize to only recalculate affected households?

---

## Notes

- The domain layer (`app/Domain/Members/Member.php`) already expects a single `householdId`, so this refactor aligns the persistence layer with the domain model.
- The repository was already treating it as one-to-many by taking `first()`, so behavior should remain consistent.
- The unique constraint on `member_id` in the pivot table already prevented multiple households per member, so this is truly just a refactor, not a behavior change.
- Billing logic depends on household membership, so thorough testing of the observer changes is critical.

---

## Success Criteria

- [ ] Member can have 0 or 1 household (not many)
- [ ] All household management happens from MemberResource
- [ ] Can view all household members from a member's page
- [ ] Can create new household for a member
- [ ] Can join existing household
- [ ] Can remove member from household
- [ ] Billing recalculation works correctly
- [ ] All tests pass
- [ ] No regressions in existing functionality
- [ ] Code is cleaner and more maintainable

---

## Post-Implementation Enhancements

Consider these follow-up improvements:

1. **Add household name/label field** for easier identification
2. **Household address** (if different from members' addresses)
3. **Primary household member** designation
4. **Household statistics** (total members, age distribution, etc.)
5. **Bulk household operations** (move multiple members at once)
6. **Household history** (track member join/leave dates)
7. **Scheduled job** to clean up empty households
8. **Export household data** for reporting

---

**End of Implementation Plan**
