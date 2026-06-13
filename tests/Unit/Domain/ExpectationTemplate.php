<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Mockery;
use Mockery\MockInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Expectation template following the project's Mockery pattern.
 *
 * Usage: copy this file, replace `\stdClass` with the concrete dependency
 * class/interface and update the constructor/property type to `MockInterface&TargetInterface`.
 *
 * Preferred property signature in concrete files:
 *
 *     private function __construct(public MockInterface&TargetInterface $mock) {}
 *
 * Keep Expectation classes tiny: expose the configured mock and a small set
 * of `expects...` methods used by tests.
 */
final readonly class ExpectationTemplate
{
    private function __construct(
        public MockInterface&CacheInterface $mock,
    ) {}

    /**
     * Create a new expectation instance.
     * Replace `\stdClass::class` with the concrete class or interface being mocked.
     */
    public static function create(): self
    {
        return new self(Mockery::mock(CacheInterface::class));
    }

    /**
     * Example expectation method. Replace with real expectation methods that
     * configure the mock's behaviour used by tests.
     */
    public function expectsGet(string $arg, mixed $return): void
    {
        $this->mock
            ->expects('get')
            ->with($arg)
            ->andReturn($return);
    }
}
