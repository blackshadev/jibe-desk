---
name: testing
description: "Activate when writing, changing, or reviewing tests. Describes this project's testing style, expectations, and when to use unit vs feature tests. Includes guidance on expectation classes used for mocked arguments."
license: MIT
metadata:
  author: project
---

# Testing Skill

Rules for writing and reviewing tests. Follow them to keep tests fast, readable, and correctly scoped.

## Quick Reference

### 1. Unit Tests → `rules/unit-tests.md`

- Domain logic, value objects, services — no DB, no HTTP layer
- Extend `Tests\UnitTestCase`; place under `tests/Unit/Domain/<Context>/<Thing>Test.php`
- Use Mockery; use Expectation classes for complex argument matching

### 2. Feature Tests → `rules/feature-tests.md`

- HTTP controllers, middleware, repository implementations, multi-layer integration
- Extend `Tests\FeatureTestCase`; place under `tests/Feature/Http/` or `tests/Feature/Infrastructure/`
- Real DB + factories; fakes (`Event::fake`, `Http::fake`) after fixture setup

### 3. Expectation Classes → `rules/expectation-classes.md`

- Wrap Mockery mocks in a small `readonly` class with `expects…` methods
- Avoids brittle inline argument matchers; reusable across tests
- Place as `tests/Unit/Domain/<Context>/<Thing>Expectation.php`
- Template: `tests/Unit/Domain/ExpectationTemplate.php`

## Assertion Style

- Prefer static PHPUnit assertions where practical: use `self::assertSame(...)` rather than `$this->assertSame(...)` when possible. This makes tests clearer to static analysis tools and avoids accidental reliance on instance state — prefer the static form unless you specifically need an instance method.

## Test Placement

| Layer | Path |
|---|---|
| Unit domain | `tests/Unit/Domain/<Context>/<Thing>Test.php` |
| Feature HTTP | `tests/Feature/Http/<ControllerName>Test.php` |
| Feature infra | `tests/Feature/Infrastructure/<RepositoryName>Test.php` |
| Expectation class | `tests/Unit/Domain/<Context>/<Thing>Expectation.php` |

## How to Apply

1. Identify the layer being tested and open the matching rule file above.
2. Check sibling tests for existing patterns — follow those first.
3. Reuse existing Expectation classes; only create new ones when matching logic is non-trivial.
