# Unit tests

Unit tests cover domain logic, value objects, services, and other pure PHP classes where isolation and speed are paramount.

## When to write a unit test

- You are testing domain services, value objects, policies, or any class that contains business logic and does not require the Laravel HTTP/kernel or database to execute.

## Rules

- Place unit tests under `tests/Unit/...`, preferably organized by bounded context: `tests/Unit/Domain/<Context>/<Thing>Test.php`.
- Unit tests must not touch the database or Laravel HTTP layer. Use mocks and expectations for collaborators.
- Use Expectation classes for matching complex mocked arguments instead of brittle inline assertions. Place expectation classes near their tests (e.g. `tests/Unit/Domain/.../*Expectation.php`).
- All unit tests MUST extend `Tests\\UnitTestCase` (tests/UnitTestCase.php).

## Best practices

- Keep unit tests focused and fast (<10ms typical). Test a single behavior per test method.
- Prefer Mockery for mocks; use the `MockeryPHPUnitIntegration` trait in `UnitTestCase` to ensure cleanups.
- When interaction with a dependency is important, assert that the dependency was called with an Expectation object that encapsulates matching logic.
- Can use faker using the `WithFaker` trait.

## Examples (conceptual)

```
// tests/Unit/Domain/Invoices/InvoiceNumberGeneratorImplTest.php
public function test_generates_sequential_number()
{
    $clock = ClockExpectation::create()->withNow(...);
    $repo = InvoiceRepositoryExpectation::create();

    $this->invoiceGenerator->generate();

    $repo->expectsSave($clock);
}
```

## Boilerplate

```
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Tests\UnitTestCase;

class ExampleUnitTest extends UnitTestCase
{
    public function test_example(): void
    {
        $this->assertTrue(true);
    }
}
```
