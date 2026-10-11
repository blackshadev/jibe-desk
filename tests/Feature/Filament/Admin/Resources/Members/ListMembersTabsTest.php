<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources\Members;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Resources\Members\Pages\ListMembers;
use App\Models\Member;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class ListMembersTabsTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_tabs_exist_with_correct_badge_counts(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        Member::factory()->count(2)->pending()->createQuietly();
        Member::factory()->count(3)->createQuietly();
        Member::factory()->stopped()->createQuietly();

        $component = Livewire::test(ListMembers::class)->instance();
        $tabs = $component->getTabs();

        static::assertArrayHasKey('new', $tabs);
        static::assertArrayHasKey('active', $tabs);
        static::assertArrayHasKey('inactive', $tabs);

        static::assertSame('2', $tabs['new']->getBadge());
        static::assertSame('3', $tabs['active']->getBadge());
        static::assertSame('1', $tabs['inactive']->getBadge());
    }
}
