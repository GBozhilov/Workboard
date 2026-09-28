<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_loads(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Create account');
    }

    public function test_new_user_can_register(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'georgi@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'georgi@example.com',
        ]);
    }

    public function test_short_password_can_register_successfully(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Short Pass User',
            'email' => 'short@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'short@example.com',
        ]);
    }

    public function test_empty_password_is_rejected(): void
    {
        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Test User',
            'email' => 'empty-pass@example.com',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', [
            'email' => 'empty-pass@example.com',
        ]);
    }

    public function test_mismatched_password_confirmation_is_rejected(): void
    {
        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Test User',
            'email' => 'mismatch@example.com',
            'password' => '123',
            'password_confirmation' => '456',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', [
            'email' => 'mismatch@example.com',
        ]);
    }

    public function test_password_is_stored_hashed_not_plain_text(): void
    {
        $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'georgi@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'georgi@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotSame('password', $user->password);
        $this->assertTrue(Hash::check('password', $user->password));
    }
}
