<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Members;

use App\Infrastructure\Members\MembershipDbRepository;
use App\Models\Membership;
use Tests\FeatureTestCase;

final class MembershipDbRepositoryTest extends FeatureTestCase
{
    public function test_all_returns_all_memberships_with_billable_item_ids(): void
    {
        $model1 = Membership::factory()->create();
        $model2 = Membership::factory()->create();

        $repo = new MembershipDbRepository();

        $list = $repo->all();

        $ids = $list->asBillingIdList()->toIntArray();

        self::assertContains($model1->billable_item_id, $ids);
        self::assertContains($model2->billable_item_id, $ids);
    }
}
