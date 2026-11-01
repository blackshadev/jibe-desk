<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources\Members;

use App\Filament\Admin\Resources\Members\Pages\CreateMember;
use App\Filament\Admin\Resources\Members\Pages\EditMember;
use App\Filament\Admin\Resources\Members\Pages\ListMembers;
use App\Models\Member;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class MemberLidnrTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_the_legacy_lidnr_is_not_shown_on_the_list_page(): void
    {
        $this->withAuthorizedUser();
        Member::factory()->createQuietly(['lidnr' => '1001']);

        Livewire::test(ListMembers::class)
            ->assertTableColumnDoesNotExist('lidnr');
    }

    public function test_the_legacy_lidnr_is_not_editable(): void
    {
        $this->withAuthorizedUser();
        $member = Member::factory()->createQuietly(['lidnr' => '1001']);

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->assertFormFieldDoesNotExist('lidnr')
            ->assertSchemaComponentDoesNotExist('lidnr');
    }

    public function test_the_legacy_lidnr_is_not_on_the_create_form(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(CreateMember::class)
            ->assertFormFieldDoesNotExist('lidnr')
            ->assertSchemaComponentDoesNotExist('lidnr');
    }
}
