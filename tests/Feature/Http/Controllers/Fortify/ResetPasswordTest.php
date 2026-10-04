<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\FeatureTestCase;

final class ResetPasswordTest extends FeatureTestCase
{
    public function test_reset_password_page_renders_the_form(): void
    {
        $response = $this->get(route('password.reset', [
            // @mago-expect lint:no-literal-password
            'token' => 'some-token',
            'email' => 'jan@example.com',
        ]));

        $response->assertOk();
        $response->assertSee('name="token"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
        $response->assertSee('jan@example.com');
    }

    public function test_reset_password_updates_the_user_password_and_redirects_to_the_panel(): void
    {
        $user = User::factory()->createQuietly();
        $token = Password::broker('users')->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            // @mago-expect lint:no-literal-password
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ]);

        $response->assertRedirect('/admin');

        static::assertTrue(Hash::check('new-strong-password', $user->fresh()->password));
    }

    public function test_reset_password_with_an_invalid_token_fails(): void
    {
        $user = User::factory()->createQuietly();

        $response = $this->post(route('password.update'), [
            // @mago-expect lint:no-literal-password
            'token' => 'invalid-token',
            'email' => $user->email,
            // @mago-expect lint:no-literal-password
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
