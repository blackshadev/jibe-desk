<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\NewMemberWelcome;
use Tests\FeatureTestCase;

final class NewMemberWelcomeTest extends FeatureTestCase
{
    public function test_it_renders_correctly(): void
    {
        $mail = new NewMemberWelcome(
            memberName: 'Vries, Jan de',
        );

        $rendered = $mail->render();

        self::assertStringContainsString('Welkom bij Almere Centraal!', $rendered);
        self::assertStringContainsString('Beste Vries, Jan de', $rendered);
        self::assertStringContainsString('Wat leuk dat je lid wilt worden', $rendered);
        self::assertStringContainsString('twee weken', $rendered);
    }
}
