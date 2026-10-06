<?php

namespace Database\Factories;

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
            'email' => fake()->unique()->safeEmail(),
            'nik' => $this->generateNik(),
            'phone_number' => $this->generatePhone(),
            'address' => fake()->address(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Generate a unique (by construction) 16-digit NIK.
     */
    private function generateNik(): string
    {
        return str_pad((string) random_int(0, 9999999999999999), 16, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a unique (by construction) local-format Indonesian phone number.
     */
    private function generatePhone(): string
    {
        return '08' . str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
