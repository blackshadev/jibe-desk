<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Domain\Mail\OutgoingEmailStatus;
use App\Filament\Admin\Widgets\Dashboard\FailedOutgoingEmailsWidget;
use App\Models\OutgoingEmail;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class FailedOutgoingEmailsWidgetTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_it_counts_failed_outgoing_emails(): void
    {
        $this->withUserHavingRole(RoleName::TechnicalAdministration);

        OutgoingEmail::factory()->count(2)->createQuietly(['status' => OutgoingEmailStatus::Failed]);
        OutgoingEmail::factory()->createQuietly(['status' => OutgoingEmailStatus::Sent]);

        Livewire::test(FailedOutgoingEmailsWidget::class)
            ->assertSee('2');
    }

    public function test_it_is_only_visible_for_technical_administration(): void
    {
        $this->withUserHavingRole(RoleName::TechnicalAdministration);
        static::assertTrue(FailedOutgoingEmailsWidget::canView());

        $this->withUserHavingRole(RoleName::MemberAdministration);
        static::assertFalse(FailedOutgoingEmailsWidget::canView());
    }
}
