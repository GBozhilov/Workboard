<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoUserSeeder extends Seeder
{
    public static ?User $userOne = null;

    public static ?User $userTwo = null;

    public function run(): void
    {
        self::$userOne = $this->createDemoUser('user_one');
        self::$userTwo = $this->createDemoUser('user_two');
    }

    private function createDemoUser(string $key): User
    {
        $config = config("demo.{$key}");

        $email = $config['email'] ?? null;
        $password = $config['password'] ?? null;

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException(
                "Demo user [{$key}] is not configured. Set DEMO_USER_*_EMAIL and DEMO_USER_*_PASSWORD in your .env file.",
            );
        }

        return User::factory()->create([
            'name' => $config['name'],
            'email' => $email,
            'password' => Hash::make($password),
        ]);
    }
}
