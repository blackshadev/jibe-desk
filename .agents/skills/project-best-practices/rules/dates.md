# Dates

## Do Date Arithmetic with `CarbonImmutable`

Do date arithmetic with `CarbonImmutable` helpers, not string building or `strtotime`. `addMonthsNoOverflow()` is the helper for a fixed-length period on a calendar date: month-end stays month-end (31 Jan + 36 months → 31 Jan, 29 Feb + 36 months → 28 Feb).

Compare the resulting instances (`isPast()`, `isFuture()`, `lessThan()`); formatted date strings compare lexicographically and break on timezone or format changes.

Incorrect:
```php
$expiresAt = $signedAt->modify("+{$months} months"); // rolls 31 Jan over to 3 Mar
$expired = $expiresAt->format('Y-m-d') < now()->format('Y-m-d');
```

Correct:
```php
$expiresAt = CarbonImmutable::instance($signedAt)->addMonthsNoOverflow($months);
$expired = $expiresAt->isPast();
```

A domain period that must clamp to the month end belongs on the model as an `Attribute::get()` accessor (see `docs/architecture.md`), with the month count as a named constant such as `MONTHS_WITHOUT_COLLECTION`.
