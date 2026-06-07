# Testing

Every change ships with tests. The layer under test dictates the test type and base class.

## Base classes

- `tests/FeatureTestCase` — extends Laravel's `TestCase`, includes `LazilyRefreshDatabase`, `WithCachedAutowire`, `WithCachedConfig`, `WithCachedRoutes`. Use for any test that needs the framework boot, the database, the container, or HTTP.
- `tests/UnitTestCase` — extends `PHPUnit\Framework\TestCase`, includes `MockeryPHPUnitIntegration` and a `WithFaker` setup hook. Use for any test that must not boot the framework.

## Domain — `tests/Unit/Domain/<Context>/`

- Pure business logic: value objects, domain services, generators, enums, repository contracts.
- Extends `Tests\UnitTestCase`. No database, no HTTP, no container.
- Collaborators are replaced with Mockery mocks. For non-trivial argument matching, mocks are wrapped in **Expectation classes** — see `.agents/skills/testing/rules/expectation-classes.md`. An expectation class is a small `final readonly` class that exposes `expects…` methods to configure the mock in a readable, reusable way.
- Example: `tests/Unit/Domain/Invoices/InvoiceGeneratorImplTest` uses `BillableItemsViewRepositoryExpectation` and `CreateInvoiceExpectation` to drive the domain service and assert how it calls its collaborators.
- When a test only needs a clock, prefer injecting `Psr\Clock\ClockInterface` and using a `ClockExpectation` rather than reaching for a Laravel helper.
- Use the template at `tests/Unit/Domain/ExpectationTemplate.php` when adding a new expectation class.

## Laravel — `tests/Feature/...`

- Anything that needs the framework: Eloquent repository implementations, observers, HTTP controllers, middleware, console commands, service-provider wiring, policies that consult the container.
- Extends `Tests\FeatureTestCase`. The base class refreshes the database, so factories and `assertDatabaseHas` work out of the box.
- Exercise the public surface end-to-end. For repository adapters, that means real DB writes and reads; for controllers, that means HTTP requests and assertions on response and side effects.
- Use `Event::fake`, `Http::fake`, `Mail::fake` to silence cross-cutting concerns, but construct the database fixtures first.
- Recommended layout:
    - `tests/Feature/Infrastructure/<Context>/<Repository>Test.php` — Eloquent adapter tests.
    - `tests/Feature/Http/<Controller>Test.php` — HTTP controller tests.
    - `tests/Feature/Observers/<Observer>Test.php` — observer behaviour tests.
    - `tests/Feature/Console/<Command>Test.php` — Artisan command tests.

## Filament — `tests/Feature/Filament/...`

- Filament code is exercised through `Livewire` against the real panel.
- Extends `Tests\FeatureTestCase` and uses `Livewire::test(...)` to mount the Filament page or resource, drive its actions, and assert on the resulting database state.
- Filament tests are **optional** — every resource is encouraged to have at least smoke tests for list/create/edit and any custom header or row actions, but the bulk of the behaviour should be covered at the domain and infrastructure layers.
- Recommended layout: mirror the resource structure, e.g. `tests/Feature/Filament/StorageSpaces/StorageSpaceResourceTest.php`.

## Test placement summary

| Layer | Test base | Test path |
| --- | --- | --- |
| Domain | `UnitTestCase` | `tests/Unit/Domain/<Context>/<Thing>Test.php` |
| Expectation class | n/a (plain class) | `tests/Unit/Domain/<Context>/<Thing>Expectation.php` |
| Infrastructure (Eloquent adapter) | `FeatureTestCase` | `tests/Feature/Infrastructure/<Context>/<Repository>Test.php` |
| HTTP controller | `FeatureTestCase` | `tests/Feature/Http/<Controller>Test.php` |
| Observer | `FeatureTestCase` | `tests/Feature/Observers/<Observer>Test.php` |
| Console command | `FeatureTestCase` | `tests/Feature/Console/<Command>Test.php` |
| Filament resource / page | `FeatureTestCase` | `tests/Feature/Filament/<Resource>/<Resource>Test.php` |

For more detail on the testing rules and the expectation-class pattern, see `.agents/skills/testing/`.
