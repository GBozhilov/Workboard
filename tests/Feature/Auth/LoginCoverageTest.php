<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rejects_unknown_email(): void
    {
        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'unknown@workboard.test',
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_rejects_empty_email(): void
    {
        $this->from(route('login'))
            ->post(route('login'), [
                'email' => '',
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_login_rejects_empty_password(): void
    {
        User::factory()->create(['email' => 'user@workboard.test']);

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'user@workboard.test',
                'password' => '',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_login_rejects_malformed_email(): void
    {
        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'not-valid',
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_authenticated_user_is_redirected_from_login_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_login_with_remember_me_sets_remember_cookie(): void
    {
        $user = User::factory()->create([
            'email' => 'remember@workboard.test',
            'password' => 'password',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'remember@workboard.test',
            'password' => 'password',
            'remember' => 'on',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertNotNull($user->remember_token);
    }

    public function test_login_rate_limits_after_five_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'locked@workboard.test',
            'password' => 'correct-password',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))->post(route('login'), [
                'email' => 'locked@workboard.test',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'locked@workboard.test',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = session('errors');
        $this->assertNotNull($errors);
        $this->assertStringContainsString('Too many', (string) $errors->first('email'));
    }

    public function test_successful_login_clears_rate_limiter_for_email(): void
    {
        User::factory()->create([
            'email' => 'clear@workboard.test',
            'password' => 'password',
        ]);

        for ($i = 0; $i < 4; $i++) {
            $this->from(route('login'))->post(route('login'), [
                'email' => 'clear@workboard.test',
                'password' => 'wrong',
            ]);
        }

        $this->post(route('login'), [
            'email' => 'clear@workboard.test',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->post(route('logout'));

        $this->from(route('login'))->post(route('login'), [
            'email' => 'clear@workboard.test',
            'password' => 'wrong',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $errors = session('errors');
        $this->assertNotNull($errors);
        $this->assertStringNotContainsString('Too many', (string) $errors->first('email'));
    }

    public function test_guest_cannot_post_logout(): void
    {
        $this->post(route('logout'))
            ->assertRedirect(route('login'));
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $sessionId = session()->getId();

        $this->post(route('logout'))->assertRedirect('/');

        $this->assertGuest();
        $this->assertNotSame($sessionId, session()->getId());
    }
}
