<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Log in');
    }

    public function test_valid_user_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'georgi@example.com',
            'password' => 'password',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'georgi@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'georgi@example.com',
            'password' => 'password',
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'georgi@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_guests_cannot_access_authenticated_area(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_access_authenticated_area(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee($user->name);
    }
}
