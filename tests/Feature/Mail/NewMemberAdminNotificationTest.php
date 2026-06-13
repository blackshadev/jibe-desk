<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Domain\Members\MemberId;
use App\Mail\NewMemberAdminNotification;
use App\Domain\Registration\MembershipData;
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

        self::assertStringContainsString('Nieuwe aanmelding', $rendered);
        self::assertStringContainsString('Vries, Jan de', $rendered);
        self::assertStringContainsString('Reguliere surflessen: Ja', $rendered);
        self::assertStringContainsString('RTC: Nee', $rendered);
        self::assertStringContainsString('Clubhuis toegang: Ja', $rendered);
        self::assertStringContainsString('Board opslag: Nee', $rendered);
        self::assertStringContainsString('Watersportbond nummer: 12345', $rendered);
        self::assertStringContainsString('Bekijk lid in administratie', $rendered);
    }

    public function test_it_shows_default_text_for_empty_federation_number(): void
    {
        $mail = new NewMemberAdminNotification(
            memberId: MemberId::create(1),
            memberName: 'Berg, Jan van',
            membershipData: MembershipData::createDefault(),
        );

        $rendered = $mail->render();

        self::assertStringContainsString('Watersportbond nummer: Niet opgegeven', $rendered);
    }
}
