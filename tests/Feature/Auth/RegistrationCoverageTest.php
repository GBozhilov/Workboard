<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validRegistrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Coverage User',
            'email' => 'coverage-user@workboard.test',
            'password' => 'secret-value',
            'password_confirmation' => 'secret-value',
        ], $overrides);
    }

    public function test_authenticated_user_is_redirected_away_from_registration_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('register'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_registration_with_one_character_password_succeeds(): void
    {
        $this->post(route('register'), $this->validRegistrationPayload([
            'email' => 'one-char@workboard.test',
            'password' => 'x',
            'password_confirmation' => 'x',
        ]))->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_registration_rejects_missing_password_field(): void
    {
        $payload = $this->validRegistrationPayload(['email' => 'missing-pass@workboard.test']);
        unset($payload['password'], $payload['password_confirmation']);

        $this->from(route('register'))
            ->post(route('register'), $payload)
            ->assertSessionHasErrors('password');
    }

    public function test_registration_rejects_missing_password_confirmation(): void
    {
        $this->from(route('register'))
            ->post(route('register'), $this->validRegistrationPayload([
                'email' => 'no-confirm@workboard.test',
                'password_confirmation' => '',
            ]))
            ->assertSessionHasErrors('password');
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@workboard.test']);

        $this->from(route('register'))
            ->post(route('register'), $this->validRegistrationPayload(['email' => 'taken@workboard.test']))
            ->assertSessionHasErrors('email');
    }

    public function test_registration_rejects_mixed_case_email(): void
    {
        $this->from(route('register'))
            ->post(route('register'), $this->validRegistrationPayload([
                'email' => 'MixedCase@Workboard.Test',
            ]))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'mixedcase@workboard.test']);
    }

    public function test_registration_rejects_missing_name(): void
    {
        $this->from(route('register'))
            ->post(route('register'), $this->validRegistrationPayload([
                'name' => '',
                'email' => 'noname@workboard.test',
            ]))
            ->assertSessionHasErrors('name');
    }

    public function test_registration_rejects_whitespace_only_name(): void
    {
        $this->from(route('register'))
            ->post(route('register'), $this->validRegistrationPayload([
                'name' => '   ',
                'email' => 'blankname@workboard.test',
            ]))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('users', ['email' => 'blankname@workboard.test']);
    }

    public function test_registration_accepts_name_at_max_length(): void
    {
        $this->post(route('register'), $this->validRegistrationPayload([
            'name' => str_repeat('n', 255),
            'email' => 'maxname@workboard.test',
        ]))->assertRedirect(route('dashboard'));
    }

    public function test_registration_rejects_name_over_max_length(): void
    {
        $this->from(route('register'))
            ->post(route('register'), $this->validRegistrationPayload([
                'name' => str_repeat('n', 256),
                'email' => 'longname@workboard.test',
            ]))
            ->assertSessionHasErrors('name');
    }

    public function test_registration_rejects_email_over_max_length(): void
    {
        $email = str_repeat('a', 250).'@t.com';

        $this->from(route('register'))
            ->post(route('register'), $this->validRegistrationPayload(['email' => $email]))
            ->assertSessionHasErrors('email');
    }

    public function test_registration_persists_expected_user_fields(): void
    {
        $this->post(route('register'), $this->validRegistrationPayload([
            'name' => 'Persisted Name',
            'email' => 'persisted@workboard.test',
        ]));

        $this->assertDatabaseHas('users', [
            'name' => 'Persisted Name',
            'email' => 'persisted@workboard.test',
        ]);
    }

    public function test_registration_hashes_password_before_storage(): void
    {
        $this->post(route('register'), $this->validRegistrationPayload([
            'email' => 'hashcheck@workboard.test',
            'password' => 'plain-text-secret',
            'password_confirmation' => 'plain-text-secret',
        ]));

        $user = User::where('email', 'hashcheck@workboard.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('plain-text-secret', $user->password));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidRegistrationEmailProvider(): array
    {
        return [
            'missing at sign' => ['not-an-email'],
            'missing domain' => ['user@'],
            'contains spaces' => ['user name@example.com'],
            'plain text' => ['plaintext'],
        ];
    }

    #[DataProvider('invalidRegistrationEmailProvider')]
    public function test_registration_rejects_invalid_email_format(string $email): void
    {
        $this->from(route('register'))
            ->post(route('register'), $this->validRegistrationPayload(['email' => $email]))
            ->assertSessionHasErrors('email');
    }
}
