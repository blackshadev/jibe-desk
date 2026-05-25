# Feature tests

Feature tests are the repository's integration-level tests. They verify wiring, database interactions, HTTP controllers, and repository implementations.

## When to write a feature test

- You are testing a Laravel HTTP controller or middleware behavior.
- You are testing a repository implementation (an adapter in app/Infrastructure or similar) and want to validate SQL, casts, scopes, or relationship wiring.
- You need to exercise multiple components together (controller → action → repository → DB).

## Rules

- Place feature tests under `tests/Feature/...` with clear naming (e.g. `tests/Feature/Http/InvoiceControllerTest.php`, `tests/Feature/Infrastructure/InvoiceRepositoryTest.php`).
- Feature tests should use the real test database with migrations and factories to validate queries and persistence behavior.
- Exercise the public surface of repositories and controllers rather than mocking their internals. The goal is to verify integration and side effects.
- All feature tests MUST extend `Tests\\FeatureTestCase` (tests/FeatureTestCase.php). That base class includes shared traits such as `LazilyRefreshDatabase`, `WithCachedRoutes`, and `WithCachedConfig`.

## Best practices

- Use factories and factory states to set up data; prefer `recycle()` when appropriate.
- Use fakes for external systems (Event::fake, Http::fake, Mail::fake) but create fakes after constructing database fixtures where order matters.
- Keep feature tests focused: assert end-to-end behaviour (response status, JSON shape, database changes) rather than internal method calls.

## Examples (conceptual)

```
// tests/Feature/Http/InvoiceControllerTest.php
public function test_store_creates_invoice_in_db()
{
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('invoices.store'), [...payload...])
        ->assertStatus(201)
        ->assertJsonStructure(['id', 'number']);

    $this->assertDatabaseHas('invoices', ['number' => 'INV-0001']);
}
```

## Boilerplate

```
<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Tests\FeatureTestCase;

class ExampleFeatureTest extends FeatureTestCase
{
    public function test_example(): void
    {
        $this->get('/')->assertStatus(200);
    }
}
```
