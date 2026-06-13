<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Override;
use PHPUnit\Framework\TestCase;

abstract class UnitTestCase extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[Override]
    protected function setup(): void
    {
        parent::setup();
        $uses = $this->traitsUsedByTest ?? array_flip(class_uses_recursive(static::class));

        // @mago-expect lint:no-isset
        if (isset($uses[WithFaker::class])) {
            /** @phpstan-ignore method.notFound */
            $this->setUpFaker();
        }
    }
}
