<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * The POS account model is username + role based, there is no email verification.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password123'),
            'role' => 'admin',
        ];
    }

    public function ownerMakanan(): static
    {
        return $this->state(fn () => ['role' => 'owner_makanan']);
    }

    public function ownerMinuman(): static
    {
        return $this->state(fn () => ['role' => 'owner_minuman']);
    }
}
