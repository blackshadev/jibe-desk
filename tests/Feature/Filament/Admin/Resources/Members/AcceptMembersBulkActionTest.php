<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources\Members;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Resources\Members\Pages\ListMembers;
use App\Infrastructure\Mail\MailMailable;
use App\Models\Member;
use Database\Seeders\MembershipSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Override;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class AcceptMembersBulkActionTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MembershipSeeder::class);
    }

    public function test_bulk_action_accepts_selected_pending_members(): void
    {
        Mail::fake();

        $this->withUserHavingRole(RoleName::MemberAdministration);

        $members = Member::factory()->count(2)->pending()->createQuietly();

        Livewire::test(ListMembers::class)
            ->callTableBulkAction('acceptMembers', $members);

        foreach ($members as $member) {
            $member->refresh();
            static::assertNotNull($member->accepted_at);
            static::assertNotNull($member->user_id);
        }

        Mail::assertQueued(MailMailable::class, 2);
    }
}
