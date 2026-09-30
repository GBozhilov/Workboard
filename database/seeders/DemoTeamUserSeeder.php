<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoTeamUserSeeder extends Seeder
{
    /**
     * @var Collection<int, User>|null
     */
    public static ?Collection $teamMembers = null;

    /**
     * @var list<array{name: string, email: string}>
     */
    private const TEAM = [
        ['name' => 'Maria Ivanova', 'email' => 'maria.ivanova@workboard.demo'],
        ['name' => 'Petar Dimitrov', 'email' => 'petar.dimitrov@workboard.demo'],
        ['name' => 'Elena Petrova', 'email' => 'elena.petrova@workboard.demo'],
        ['name' => 'Ivan Georgiev', 'email' => 'ivan.georgiev@workboard.demo'],
        ['name' => 'Sofia Nikolova', 'email' => 'sofia.nikolova@workboard.demo'],
    ];

    public function run(): void
    {
        self::$teamMembers = collect(self::TEAM)->map(function (array $person): User {
            return User::factory()->create([
                'name' => $person['name'],
                'email' => $person['email'],
            ]);
        });
    }
}
