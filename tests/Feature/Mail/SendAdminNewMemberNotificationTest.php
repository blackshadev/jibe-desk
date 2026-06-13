<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Domain\Members\Listeners\SendAdminNewMemberNotification;
use App\Domain\Members\MemberId;
use App\Domain\Members\Events\NewMemberRegistration;
use App\Domain\Registration\MembershipData;
use App\Mail\NewMemberAdminNotification;
use Illuminate\Support\Facades\Mail;
use Tests\FeatureTestCase;

final class SendAdminNewMemberNotificationTest extends FeatureTestCase
{
    public function test_it_sends_admin_notification(): void
    {
        Mail::fake();

        $event = new NewMemberRegistration(
            memberId: MemberId::create(1),
            memberName: 'Vries, Jan de',
            memberEmail: 'jan@example.com',
            membershipData: MembershipData::createDefault(),
        );

        $listener = new SendAdminNewMemberNotification();
        $listener->handle($event);

        Mail::assertSent(NewMemberAdminNotification::class, function (NewMemberAdminNotification $mail): bool {
            return $mail->hasTo(config('mail.admin.address'))
                && $mail->hasSubject('Nieuwe aanmelding: Vries, Jan de');
        });
    }
}
