# Architecture

WSV Admin follows a layered, domain-centric architecture on top of Laravel and Filament. The goal is to keep the core business rules independent of the framework so they remain easy to test, reason about, and evolve.

## Layers at a glance

```
┌─────────────────────────────────────────────────────────────────┐
│  Filament  (app/Filament)                                       │
│  Admin panel UI: Resources, Pages, Widgets, Schemas, Actions    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Laravel   (app/Http, app/Models, app/Observers, app/Policies,  │
│             app/Rules, app/Console, app/Actions, app/Providers, │
│             app/Formatters)                                     │
│  Framework-specific concerns: Eloquent models, HTTP, auth,      │
│  console, validation rules, observers, policies, formatters.    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Domain    (app/Domain)                                         │
│  Pure PHP business logic: value objects, domain services,       │
│  repository interfaces, enums. No Eloquent, no HTTP.            │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Infrastructure (app/Infrastructure)                            │
│  Adapters that implement domain interfaces using Eloquent and   │
│  the database. Bridges domain and Laravel.                      │
└─────────────────────────────────────────────────────────────────┘
```

Dependencies only point downward: Filament and Laravel depend on the domain, the domain depends on nothing in this project, and infrastructure depends on both the domain and Laravel.

## Domain layer — `app/Domain/`

The domain layer holds the business model. It is pure PHP: no Eloquent, no HTTP, no facades.

- Organised by bounded context: `Activities`, `Invoices`, `Members`, plus shared primitives like `NumericId`.
- Contains:
    - **Repository interfaces** (e.g. `InvoiceRepository`, `MemberRepository`) marked with `#[Autowire]` so they are autowired by interface.
    - **Value objects** (e.g. `InvoiceId`, `MemberId`, `CompoundPrice`, `InvoiceNumber`, `BillableItemList`).
    - **Domain services / generators** (e.g. `InvoiceGeneratorImpl`, `InvoiceNumberGeneratorImpl`, `InvoiceBatchGeneratorImpl`).
    - **Enums** (e.g. `Gender`, `InvoiceStatus`).
- Depends on small libraries only (`psr/clock`, `webmozart/assert`, `jeroen-g/autowire`).
- Collaborators are injected as interfaces so the domain never knows about Eloquent or the database.

## Infrastructure layer — `app/Infrastructure/`

The infrastructure layer contains the concrete implementations of the domain's repository interfaces, using Eloquent models and the database.

- One folder per bounded context (`Invoices`, `Members`, `Activities`, …).
- Example: `App\Infrastructure\Invoices\InvoiceRepositoryDb` implements `App\Domain\Invoices\InvoiceRepository`. It owns the Eloquent queries, transactions, and casts; the domain only sees the interface.
- This is the only place where Eloquent should be used to satisfy a domain contract. Domain code must not import `App\Models\*`.

## Laravel layer — `app/Http`, `app/Models`, `app/Observers`, `app/Policies`, `app/Rules`, `app/Console`, `app/Actions`, `app/Providers`, `app/Formatters`

The Laravel layer is the framework-facing code. It owns:

- **Eloquent models** (`app/Models`) — the persistence schema, relationships, casts, accessors. They cast to domain enums and value objects where it makes sense, but they are framework types, not domain types.
- **HTTP controllers** (`app/Http/Controllers`) — translate requests into calls to domain services or infrastructure adapters.
- **Observers** (`app/Observers`) — react to Eloquent model events and translate them into domain-side work.
- **Policies** (`app/Policies`) — authorisation rules for actions.
- **Validation rules** (`app/Rules`) — custom `ValidationRule` implementations, e.g. `NoOverlappingStorageSpaceRental`.
- **Console commands** (`app/Console/Commands`) — Artisan entry points.
- **Fortify actions** (`app/Actions/Fortify`) — authentication actions wired up by `FortifyServiceProvider`.
- **Formatters** (`app/Formatters`) — display-layer formatting helpers.
- **Service providers** (`app/Providers`) — bind interfaces to implementations (e.g. `AppServiceProvider` binds `Psr\Clock\ClockInterface` to `Carbon\FactoryImmutable`) and configure framework features (Fortify, the Filament panel).

This layer is allowed to know about both the domain and Laravel. Domain services and repository interfaces are resolved through the container and called as if they were any other collaborator.

## Filament layer — `app/Filament/Admin`

Filament is the user-facing admin panel. It is intentionally thin.

- **Resources** (`app/Filament/Admin/Resources/<Thing>`) — group a `Resource` class with its `Pages` (List/Create/Edit), `Schemas` (form definitions), `Tables` (table definitions), `RelationManagers`, and `Actions` (header / row actions such as `GenerateStorageSpacesAction`).
- **Pages** (`app/Filament/Admin/Pages`) — standalone pages such as the dashboard.
- **Navigation**, **Labels**, **Widgets**, **Utils** — cross-resource helpers and chrome.
- The panel is registered in `app/Providers/Filament/AdminPanelProvider.php`.

Filament resources typically delegate their actual work to the domain or to infrastructure via injected services. For example, the bulk "generate storage spaces" action calls a Filament `Action`, but the underlying creation of the records is performed through an Eloquent model — the resource is a UI adapter, not a place for business logic.

## Cross-cutting wiring

- **Autowiring** is handled by `jeroen-g/autowire`. Domain interfaces carry `#[Autowire]` and are resolved to their `App\Infrastructure\…` implementation by the container. This keeps `Domain` free of `AppServiceProvider::bind()` calls for repository contracts.
- **The clock** is bound in `AppServiceProvider` to `Carbon\FactoryImmutable`, so domain services that depend on `Psr\Clock\ClockInterface` can be tested with a fixed clock.
- **Authentication** is wired in `FortifyServiceProvider`; the user-facing auth flows live in `app/Actions/Fortify` and `app/Http/Controllers`.
