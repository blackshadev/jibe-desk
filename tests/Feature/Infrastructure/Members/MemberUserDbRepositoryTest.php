<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Members;

use App\Domain\Members\MemberId;
use App\Infrastructure\Members\MemberUserDbRepository;
use App\Models\Member;
use App\Models\User;
use Override;
use Tests\FeatureTestCase;

final class MemberUserDbRepositoryTest extends FeatureTestCase
{
    protected MemberUserDbRepository $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new MemberUserDbRepository();
    }

    public function test_it_creates_a_user_and_links_it_to_the_member(): void
    {
        $member = Member::factory()->createQuietly();

        $this->subject->provision(MemberId::create($member->id));

        $member->refresh();

        static::assertNotNull($member->user_id);

        $user = User::findOrFail($member->user_id);
        static::assertSame($member->email, $user->email);
        static::assertNotNull($user->email_verified_at);
    }

    public function test_it_is_a_noop_when_a_user_already_exists(): void
    {
        $user = User::factory()->createQuietly();
        $member = Member::factory()->createQuietly(['user_id' => $user->id]);

        $this->subject->provision(MemberId::create($member->id));

        static::assertSame($user->id, $member->fresh()->user_id);
    }

    public function test_it_is_a_noop_when_the_member_does_not_exist(): void
    {
        $this->subject->provision(MemberId::create(999_999));

        $this->assertDatabaseCount('users', 0);
    }
}
