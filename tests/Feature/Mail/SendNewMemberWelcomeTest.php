<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Domain\Members\Events\NewMemberRegistration;
use App\Domain\Members\Listeners\SendNewMemberWelcome;
use App\Domain\Members\MemberId;
use App\Domain\Registration\MembershipData;
use App\Mail\Registration\NewMemberWelcome;
use Illuminate\Support\Facades\Mail;
use Tests\FeatureTestCase;

final class SendNewMemberWelcomeTest extends FeatureTestCase
{
    public function test_it_sends_welcome_email_to_new_member(): void
    {
        Mail::fake();

        $event = new NewMemberRegistration(
            memberId: MemberId::create(1),
            memberName: 'Vries, Jan de',
            memberEmail: 'jan@example.com',
            membershipData: MembershipData::createDefault(),
        );

        $listener = new SendNewMemberWelcome();
        $listener->handle($event);

        Mail::assertQueued(
            NewMemberWelcome::class,
            static fn (NewMemberWelcome $mail): bool => $mail->hasTo('jan@example.com') && $mail->hasSubject('Welkom bij Almere Centraal!'),
        );
    }
}
