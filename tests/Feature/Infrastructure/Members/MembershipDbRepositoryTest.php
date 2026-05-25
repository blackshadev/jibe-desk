<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Members;

use App\Domain\Members\MembershipId;
use App\Infrastructure\Members\MembershipDbRepository;
use App\Models\Membership;
use App\Models\Membership as MembershipModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    public function test_get_by_id_returns_membership_domain_object(): void
    {
        $model = MembershipModel::factory()->create();

        $repo = new MembershipDbRepository();

        $domain = $repo->getById(MembershipId::create($model->id));

        self::assertSame($model->id, $domain->id->value);
        self::assertSame($model->billable_item_id, $domain->billableItemId->value);
    }

    public function test_get_by_id_throws_when_membership_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $repo = new MembershipDbRepository();

        $repo->getById(MembershipId::create(999999));
    }
}
