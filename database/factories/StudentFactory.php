<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'status' => StudentStatus::Active,
            'notes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => StudentStatus::Pending]);
    }

    /**
     * Give the student their own login account.
     */
    public function withAccount(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory()->afterCreating(fn (User $user) => $user->assignRole(Role::Student)),
        ]);
    }
}
