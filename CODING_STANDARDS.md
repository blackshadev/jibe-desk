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

## Trust the schema's constraints

The database enforces referential and uniqueness invariants: a foreign key rejects an unknown `member_id`, a partial unique index rejects a second active mandate. A repository that pre-checks or locks the parent row for the same reason adds a query and a race, not safety. Let the write fail, and translate the constraint violation into a domain error when the caller needs one.

## Fix the method, don't silence the rule

A `mago-expect` comment (including `mago-expect lint:halstead`) switches off a rule whose threshold this repo tuned for a reason. Reach the threshold with structure instead: split the method into named steps (`isExportable`, `mandateIdFromRow`, `lastCollectedAtFromRow`), extract one mapper per row shape, and give a repeated array row a `@phpstan-type` alias so the query and its filter share one annotation. A new suppression comment is review-blocking.

## A comment adds what the code cannot

PHPDoc earns its line by carrying what the type cannot say: the shape (`@return list<ExportReadinessIssue>`, `@param array{id: int, name: string}`), a constraint, or a `@throws`. A block that only restates the class name, the method name, or the parameter types goes — it is one more line to keep in sync and nothing to read. A class-level `/** Assesses collection readiness. */` above `CollectionReadinessAssessor` is the archetype to delete.

The same bar rules out narration anywhere in the file. A docblock on an enum case that spells out the case name, or on a method that must run after another workflow step, restates what the case name and the caller's body already say; delete the comment rather than keep it in sync by hand. This refines the global "prefer PHPDoc over inline comments": the shape stays a docblock, the narration is deleted.

## Constructor argument lists stay flat

An argument derived by a call is assigned to a named variable first, so the object construction reads as a list of values. `new SepaExportInvoice(lastCollectedAt: self::lastCollectedAtFromRow($row), sequenceType: $this->sequenceResolver->resolve(…))` buries the derivation two levels deep; assign `$lastCollectedAt` and `$sequenceType` above it. The same move applies inside a closure body, not only a method body.

A nullable column parsed into a value object gets a named static constructor — `SequenceType::optionallyFrom($row->sepa_sequence_type)` — rather than a ternary or an `if` at the call site.

## Model absence as an empty value, not `null`

A nullable return pushes a null check into every caller and every composition, so "nothing" becomes a value the caller can use: `list<Issue>` with `[]`, an empty string, or a list value object whose lookup answers `[]` and whose predicate answers `false` for a category that is not there. `assessIban()` and its siblings return `[]` and the parent spreads them; `SepaExportInvoiceList::forCategory()` returns `[]` for a category with none, so the export guards with `hasForCategory()` instead of testing for `null` (see `.agents/skills/project-best-practices/rules/collections.md`).

`null` earns its place where the domain models an absence — a member with no mandate yet, an unknown lookup — and the signature says so (`?MandateReadiness`). Even then, hand the caller something it can ask rather than something it must test: `SequenceType::optionallyFrom()` turns a nullable column into a VO at the edge.

## Rare branch first, common path as the fallthrough

Order the branches by likelihood and let the exceptional case return early: a credit in a debit batch is the rare one, so `if ($candidate->isCredit()) { return $this->assessCreditCandidate(…); }` comes first and the debit assessment is the fallthrough. The reader then meets the common path last, at the same nesting level as the method body.

## Break a compound condition into guards

A condition that decides a value reads as a stack of guard clauses: each rejecting case returns early, and the happy path is the last statement, built from variables named above it. Packing the whole decision into one nested ternary is not a substitute. Inside a guard, a nullable chain uses the nullsafe operator (`$batch?->exported_at !== null`) instead of a nested `if`, and the guard states only what the query did not already enforce on that row — a repeat of the `where` clause above it is noise.

## Put the predicate on the value object

A state test that appears at more than one call site becomes a named predicate on the value object: `SepaExportInvoice::isCredit()` instead of `amountInCents() < 0` in the assessor and again in the export. Name it for the domain question, not the field: a zero-amount candidate is neither credit nor debit, so a guard that means "not a debit" stays `!isDebit()`.

## Return a value object, not an associative array

A helper that hands back more than one thing returns a small `readonly` value object with typed properties instead of an associative array. The class sits beside the code that produces it, so an infrastructure helper owns its VO in the infrastructure layer. An array costs a `@phpstan-type` on every consumer and a mistyped key that reads as missing; the VO fails at construction and gives PHPStan a member to resolve.

## The handler decides whether it has work

The caller dispatches work for every id it holds and each handler skips its own no-ops: a job returns early when the subject is already settled or already handled. Filtering candidates at the dispatch site hides the rule in the caller and leaves the handler unsafe to dispatch from anywhere else — it usually only exists because a repository action was made to return ids for the caller to filter (see the repository *action* bullet in `docs/architecture.md`).
