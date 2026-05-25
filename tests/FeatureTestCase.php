<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithCachedConfig;
use Illuminate\Foundation\Testing\WithCachedRoutes;
use JeroenG\Autowire\Testing\WithCachedAutowire;

abstract class FeatureTestCase extends BaseTestCase
{
    use LazilyRefreshDatabase;
    use WithCachedAutowire;
    use WithCachedConfig;
    use WithCachedRoutes;

    // Use the base TestCase createApplication implementation from the framework.
}
