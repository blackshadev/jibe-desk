<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Domain\Members\MemberId;
use App\Domain\Registration\MembershipData;
use App\Mail\Registration\NewMemberAdminNotification;
use Tests\FeatureTestCase;

final class NewMemberAdminNotificationTest extends FeatureTestCase
{
    public function test_it_renders_correctly(): void
    {
        $membershipData = new MembershipData(
            regularWindsurfingLessons: true,
            rtc: false,
            clubhouseAccess: true,
            boardStorage: false,
            watersportFederationNumber: '12345',
        );

        $mail = new NewMemberAdminNotification(
            memberId: MemberId::create(42),
            memberName: 'Vries, Jan de',
            membershipData: $membershipData,
        );

        $rendered = $mail->render();

        static::assertStringContainsString('Nieuwe aanmelding', $rendered);
        static::assertStringContainsString('Vries, Jan de', $rendered);
        static::assertStringContainsString('Reguliere surflessen: Ja', $rendered);
        static::assertStringContainsString('RTC: Nee', $rendered);
        static::assertStringContainsString('Clubhuis toegang: Ja', $rendered);
        static::assertStringContainsString('Board opslag: Nee', $rendered);
        static::assertStringContainsString('Watersportbond nummer: 12345', $rendered);
        static::assertStringContainsString('Bekijk lid in administratie', $rendered);
    }

    public function test_it_shows_default_text_for_empty_federation_number(): void
    {
        $mail = new NewMemberAdminNotification(
            memberId: MemberId::create(1),
            memberName: 'Berg, Jan van',
            membershipData: MembershipData::createDefault(),
        );

        $rendered = $mail->render();

        static::assertStringContainsString('Watersportbond nummer: Niet opgegeven', $rendered);
    }
}
