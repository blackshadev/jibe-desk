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

        static::assertStringContainsString('Welkom bij Almere Centraal!', $rendered);
        static::assertStringContainsString('Beste Vries, Jan de', $rendered);
        static::assertStringContainsString('Wat leuk dat je lid wilt worden', $rendered);
        static::assertStringContainsString('twee weken', $rendered);
    }
}
