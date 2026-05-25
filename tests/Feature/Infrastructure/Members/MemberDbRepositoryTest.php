<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Members;

use App\Domain\Members\MemberId;
use App\Infrastructure\Members\MemberDbRepository;
use App\Models\Member;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\FeatureTestCase;

final class MemberDbRepositoryTest extends FeatureTestCase
{
    public function test_get_by_id_returns_member_domain_object(): void
    {
        $model = Member::factory()->createQuietly(['is_volunteer' => true]);

        $repo = new MemberDbRepository();

        $domain = $repo->getById(MemberId::create($model->id));

        self::assertSame($model->id, $domain->id->value);
        self::assertSame($model->membership_id, $domain->membershipId->value);
        self::assertTrue($domain->isVolunteer);
    }

    public function test_get_by_id_throws_when_member_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $repo = new MemberDbRepository();

        $repo->getById(MemberId::create(999999));
    }
}
