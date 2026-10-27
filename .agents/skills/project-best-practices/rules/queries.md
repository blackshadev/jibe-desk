# Queries

## Push Filtering and Ordering into the Query

A query that hydrates every row so PHP can pick one per key pays for every row it throws away. Express the predicate and the order in SQL, then read the one value you need.

Incorrect:
```php
$latest = [];
foreach (PaymentInformation::all() as $paymentInformation) {
    $latest[$paymentInformation->member_id] = $paymentInformation;
}
```

Correct:
```php
$members = Member::with('currentPaymentInformation')->get();
```

When the same per-parent pick repeats, define it once as a relation and reuse it through `with()`.

## Model "Current X per Parent" as a Relation

More than one query needing the same per-parent pick is the signal to put it on the parent as a relation once.

`ofMany()`/`latestOfMany()` orders by columns only, which cannot express "the unrevoked row wins, otherwise the newest revoked one". Order the pick inside a correlated subquery and attach it with a basic `where()` condition that carries the subquery; `orderBy()` + `limit()` on the relation itself is dropped by `joinRelationship()` and breaks eager loading.

```php
/** @return HasOne<PaymentInformation, $this> */
public function currentPaymentInformation(): HasOne
{
    return $this->hasOne(PaymentInformation::class)->where(
        'payment_information.id',
        DB::raw('(select current_mandate.id
                from payment_information as current_mandate
                where current_mandate.member_id = payment_information.member_id
                order by current_mandate.revoked_at is null desc, current_mandate.id desc
                limit 1)'),
    );
}
```

Cover both lazy and eager loading in the model test: a relation that works lazily can still fall over when eager-loaded.

## A Querying Class Is a Repository

A class whose job is querying the database is `XRepository` (domain interface) plus `XRepositoryDb` (implementation), even when it starts life as a `…Service`. The repository owns the query and the write: callers pass an id, a filter or a value object, never a pre-fetched list of ids. See `docs/architecture.md`.
