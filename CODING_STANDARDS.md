# Coding Standards

Reviewed at merge time. This file records the judgement calls and conventions a
reviewer enforces that no automated check can. Deterministic checks live in
`mago.toml` and `phpstan*.neon`, run by `./Taskfile lint` (wired into the
pre-commit hook) — do not restate those here.

## Constructor signature changes ripple into call sites

When a value object or entity under `app/Domain/**` changes its constructor
signature, grep the whole tree for `new <Entity>(` (app *and* tests) and update
every call site. A plan that names only "the tests in this domain" will miss
callers in other domains' tests — the domain `Member` entity is constructed in
billing-applicator tests, for example. Do the grep at review time, not when the
suite goes red.

## Casts: `datetime`, not `timestamp`, when the value is consumed as a date

Laravel's `timestamp` cast returns an int; `datetime` returns a
`DateTimeInterface`. Use `datetime` (or `immutable_datetime`) for any column
passed into a `DateTimeInterface`-typed parameter, or a `TypeError` surfaces
downstream. 

## PHPStan conventions

Enforced by `./Taskfile stan`; written here so a new class satisfies them the
first time instead of discovering the message.

- Every class under `app/Domain/**` needs a matching `tests/Unit/**` test
  (`extrastan.UnitTestRequired`). Create it alongside the class.
- `@phpstan-ignore domain.dependency` sits on the `use` line, not the usage line —
  the rule matches import statements. See `NewMemberAdminNotification.php`.
- Eloquent scope calls from Filament closures (`->pending()`, `->active()`,
  `->accepted()`, `modifyQueryUsing`) resolve on `Builder` and need
  `@phpstan-ignore-next-line method.notFound`.
