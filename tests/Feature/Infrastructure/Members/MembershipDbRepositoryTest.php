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

        $ids = $repo->all()->asBillingIdList()->toIntArray();

        static::assertContains($model1->adult_billable_item_id, $ids);
        static::assertContains($model1->kids_billable_item_id, $ids);
        static::assertContains($model2->adult_billable_item_id, $ids);
        static::assertContains($model2->kids_billable_item_id, $ids);
    }

    public function test_get_by_id_returns_membership_domain_object(): void
    {
        $model = MembershipModel::factory()->create();

        $repo = new MembershipDbRepository();

        $domain = $repo->getById(MembershipId::create($model->id));

        static::assertSame($model->id, $domain->id->value);
        static::assertSame($model->adult_billable_item_id, $domain->adultBillableItemId->value);
        static::assertSame($model->kids_billable_item_id, $domain->kidsBillableItemId->value);
    }

    public function test_get_by_id_throws_when_membership_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $repo = new MembershipDbRepository();

        $repo->getById(MembershipId::create(999_999));
    }

    public function test_get_default_returns_default_membership_id(): void
    {
        $model = MembershipModel::factory()->createQuietly(['is_default' => true]);

        $repo = new MembershipDbRepository();

        $defaultId = $repo->getDefault();

        static::assertSame($model->id, $defaultId->value);
    }

    public function test_get_default_throws_when_no_default_exists(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $repo = new MembershipDbRepository();

        $repo->getDefault();
    }
}
