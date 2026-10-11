<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources\Members;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Resources\Members\Pages\ViewMember;
use App\Infrastructure\Mail\MailMailable;
use App\Models\Member;
use Database\Seeders\MembershipSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Override;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class AcceptMemberActionTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MembershipSeeder::class);
    }

    public function test_action_is_visible_only_for_pending_members(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        $pending = Member::factory()->pending()->createQuietly();
        $accepted = Member::factory()->createQuietly();

        Livewire::test(ViewMember::class, ['record' => $pending->getRouteKey()])
            ->assertActionVisible('acceptMember');

        Livewire::test(ViewMember::class, ['record' => $accepted->getRouteKey()])
            ->assertActionHidden('acceptMember');
    }

    public function test_action_accepts_member_creates_user_and_sends_welcome_mail(): void
    {
        Mail::fake();

        $this->withUserHavingRole(RoleName::MemberAdministration);
        $member = Member::factory()->pending()->createQuietly();

        static::assertNull($member->user_id);

        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->callAction('acceptMember');

        $member->refresh();
        static::assertNotNull($member->accepted_at);
        static::assertNotNull($member->user_id);

        Mail::assertQueued(MailMailable::class);
    }
}
