<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Mail\OutgoingEmailStatus;
use App\Models\OutgoingEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

class OutgoingEmailFactory extends Factory
{
    protected $model = OutgoingEmail::class;

    public function definition(): array
    {
        return [
            'tracking_id' => fake()->uuid(),
            'subject' => fake()->sentence,
            'recipient_email' => fake()->email,
            'recipient_name' => fake()->name,
            'mailable_class' => self::class,
            'status' => OutgoingEmailStatus::Sent,
            'queued_at' => fake()->dateTime,
            'sent_at' => fake()->dateTime,
        ];
    }
}
