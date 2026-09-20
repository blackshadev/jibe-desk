<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use Override;
use Tests\Concerns\TestsMemberLifecycle;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

abstract class MemberLifecycleScenario extends FeatureTestCase
{
    use TestsMemberLifecycle;
    use WithAuthorizedUser;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBillingFixtures();
        $this->withAuthorizedUser();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->resetTime();

        parent::tearDown();
    }
}
