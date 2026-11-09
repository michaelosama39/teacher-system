<?php

namespace Database\Factories;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '01'.fake()->randomElement(['0', '1', '2', '5']).fake()->unique()->numerify('########'),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => ['must_change_password' => true]);
    }

    public function withoutPhone(): static
    {
        return $this->state(fn (array $attributes) => ['phone' => null]);
    }

    public function teacher(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(Role::Teacher));
    }

    public function parent(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(Role::Parent));
    }

    public function student(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(Role::Student));
    }

    /**
     * @param  list<Permission>  $permissions
     */
    public function assistant(array $permissions = []): static
    {
        return $this->afterCreating(function (User $user) use ($permissions): void {
            $user->assignRole(Role::Assistant);
            $user->givePermissionTo($permissions);
        });
    }
}
