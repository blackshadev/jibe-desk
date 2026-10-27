# Collections

## Replace `foreach` + `continue` with a Pipeline

A loop that skips rows and accumulates a subset hides the intent in the loop body. `filter()` + `mapWithKeys()` says the same thing in one chain; for plain arrays, `array_filter()` + `array_map()`.

Incorrect:
```php
$active = [];
foreach ($members as $member) {
    if ($member->revoked_at !== null) {
        continue;
    }
    $active[$member->id] = $member->mandate;
}
```

Correct:
```php
$active = $members
    ->filter(fn (Member $member): bool => $member->revoked_at === null)
    ->mapWithKeys(fn (Member $member): array => [$member->id => $member->mandate]);
```
