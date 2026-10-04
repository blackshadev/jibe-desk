<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources\Members;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Resources\Members\Pages\EditMember;
use App\Infrastructure\Mail\MailMailable;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class CreateUserAccountActionTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_action_is_visible_without_account_and_hidden_with_account(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        $member = Member::factory()->createQuietly();

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->assertActionVisible('createUserAccount');

        $user = User::factory()->createQuietly();
        $member->update(['user_id' => $user->id]);

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->assertActionHidden('createUserAccount');
    }

    public function test_action_provisions_a_user_and_sends_welcome_mail(): void
    {
        Mail::fake();

        $this->withUserHavingRole(RoleName::MemberAdministration);
        $member = Member::factory()->createQuietly();

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->callAction('createUserAccount');

        $member->refresh();
        static::assertNotNull($member->user_id);

        Mail::assertQueued(MailMailable::class);
    }
}
