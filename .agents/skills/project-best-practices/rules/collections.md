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

## Chain plain arrays with the pipe operator

PHP 8.5 is the floor, so a run of plain-array transforms reads top-to-bottom with `|>` instead of one throwaway variable per step. Pass a callable per step; first-class callables such as `array_values(...)` or `self::groupByCategory(...)` work as steps.

```php
$this->invoicesByCategory = $invoices
    |> (static fn (array $invoices): array => array_filter(
        $invoices,
        static fn (SepaExportInvoice $invoice): bool => $invoice->amountInCents() !== 0,
    ))
    |> array_values(...)
    |> self::groupByCategory(...);
```

## Merge lists instead of appending in a loop

A collector that appends with `$issues[] = …` inside a loop ties the result to the loop's control flow and mutates a variable across branches. Map each source to a list and spread it into one assignment.

```php
return [
    ...$this->assessMandateExpiration($candidate, $mandate),
    ...$this->assessSepaCandidateSequence($candidate, $mandate, $context),
    ...$this->assessIban($candidate, $mandate),
];
```

A list built over several steps grows the same way: `$issues = [...$issues, ...$candidateIssues];`, or `array_merge([], ...$issueLists)` when the sources are already a list of lists.
