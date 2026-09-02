<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Roles;
use Laravel\Sanctum\Sanctum;

it('rejects an unauthenticated request for the current user', function (): void {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('returns the current user with roles and permissions', function (): void {
    $user = User::factory()->withRole(Roles::CREDIT_MANAGER)->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.roles.0.slug', Roles::CREDIT_MANAGER);
});

it('revokes only the token that signed out', function (): void {
    $user = User::factory()->create();

    $phone = $user->createToken('phone');
    $laptop = $user->createToken('laptop');

    $this->withHeader('Authorization', "Bearer {$laptop->plainTextToken}")
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    expect($user->tokens()->pluck('name')->all())->toBe(['phone']);

    $this->withHeader('Authorization', "Bearer {$phone->plainTextToken}")
        ->getJson('/api/v1/auth/me')
        ->assertOk();
});

it('locks out an account deactivated after its token was issued', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('laptop');

    $user->update(['is_active' => false]);

    $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->getJson('/api/v1/auth/me')
        ->assertForbidden();
});
