<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use InteractsWithSanctum;
    use RefreshDatabase;

    public function test_protected_endpoint_returns_401_without_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_authenticated_user_can_access_api_user_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAsSanctum($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $this->sanctumTokenFor($user);
        $tokenId = $user->tokens()->first()->id;

        $this->withBearerToken($plainTextToken)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);

        $this->app->get('auth')->forgetGuards();

        $this->withBearerToken($plainTextToken)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_api_user_response_does_not_expose_sensitive_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAsSanctum($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonMissing(['password', 'remember_token']);
    }
}
