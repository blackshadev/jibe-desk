<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Override;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected static ?string $password;

    #[Override]
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function memberUser(): self
    {
        return $this->has(
            Member::factory()
                ->withPaymentInfo()
                ->state(function (array $attributes, Model $user) {
                    assert($user instanceof User);
                    
                    return [
                        'email' => $user->email,
                    ];
                })
        );
    }


}
