<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use Carbon\CarbonImmutable;
use Database\Seeders\ActivitySeeder;
use Database\Seeders\CostCenterSeeder;
use Database\Seeders\MembershipSeeder;
use Override;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

abstract class MemberLifecycleScenario extends FeatureTestCase
{
    use WithAuthorizedUser;

    protected function seedBillingFixtures(): void
    {
        $this->seed(CostCenterSeeder::class);
        $this->seed(ActivitySeeder::class);
        $this->seed(MembershipSeeder::class);
    }

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

    protected function travelToDate(string $date): CarbonImmutable
    {
        $now = CarbonImmutable::parse($date);

        CarbonImmutable::setTestNow($now);

        return $now;
    }

    protected function resetTime(): void
    {
        CarbonImmutable::setTestNow();
    }
}
