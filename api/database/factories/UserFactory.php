<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('08########'),
            'password' => Hash::make('password'),
            'job_title' => fake()->jobTitle(),
            'is_active' => true,
            // Declared so strict-mode attribute access holds on a model
            // straight out of the factory, before any refresh.
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * Attach a seeded role by slug, e.g. ->withRole(Roles::LOAN_OFFICER).
     */
    public function withRole(string $slug): static
    {
        return $this->afterCreating(function (User $user) use ($slug): void {
            $role = Role::query()->where('slug', $slug)->sole();

            $user->roles()->syncWithoutDetaching([$role->id]);
        });
    }
}
