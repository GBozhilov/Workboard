<?php

namespace Tests\Concerns;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait InteractsWithSanctum
{
    protected function sanctumTokenFor(User $user, string $name = 'test'): string
    {
        return $user->createToken($name)->plainTextToken;
    }

    protected function actingAsSanctum(User $user): self
    {
        Sanctum::actingAs($user);

        return $this;
    }

    protected function withBearerToken(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
