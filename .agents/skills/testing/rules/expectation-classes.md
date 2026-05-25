# Expectation classes

Small helpers that wrap a Mockery mock typed to a domain interface/class and provide clear, reusable expectation methods used by tests.

## Purpose

- Provide a single, readable place to configure mocked behaviour for a dependency. This keeps tests concise and avoids repeating Mockery setup inline.

## Where to place them

- Use the `Expectation` suffix and place them near the tests that use them: `tests/Unit/Domain/<BoundedContext>/<Thing>Expectation.php`.

## Minimal API & implementation pattern

- Use Mockery and a private constructor that stores a strongly typed mock as a public readonly property.
- Provide a static create(): self factory that returns a new instance with `Mockery::mock(Target::class)`.
- Add public methods named `expects...` to configure expectations on the mock (e.g. `expectsNow()`, `expectsGetLatestInvoiceNumber()`).

Example pattern (conceptual):

```
final readonly class FooExpectation
{
    private function __construct(public Mockery\MockInterface&FooInterface $mock) {}

    public static function create(): self
    {
        return new self(Mockery::mock(FooInterface::class));
    }

    public function expectsDoThing(Type $arg, Return $return): void
    {
        $this->mock
            ->expects('doThing')
            ->with($arg)
            ->andReturn($return);
    }
}
```

## Rules

- Keep Expectation classes tiny and focused: they should only expose the mock and a few expectation methods needed by tests.
- Name expectation methods to clearly describe the mocked call being configured (prefix `expects`).
- Prefer reusing existing Expectation classes instead of creating new ones when possible.
- Add a small unit test for an Expectation when its matching logic is non-trivial or likely to change.

## Template

- See `tests/Unit/Domain/ExpectationTemplate.php` for a minimal example to copy when creating new Expectation classes.
