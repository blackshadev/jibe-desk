<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Member;
use App\Models\PaymentInformation;
use Illuminate\Support\Str;
use Tests\FeatureTestCase;

final class PaymentInformationTest extends FeatureTestCase
{
    public function test_uuid_is_generated_on_creation(): void
    {
        $member = Member::factory()->createQuietly();
        $paymentInformation = PaymentInformation::factory()->create(['member_id' => $member->id]);

        static::assertNotEmpty($paymentInformation->uuid);
        static::assertTrue(Str::isUuid($paymentInformation->uuid));
    }
}
