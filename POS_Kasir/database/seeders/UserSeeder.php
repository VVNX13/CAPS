<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Demo accounts for the three roles.
     *
     * The password is hashed through the model's `hashed` cast, so a plain
     * string can be assigned here.
     *
     * @return list<array{username: string, name: string, email: string, password: string, role: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'username' => 'owner_makanan',
                'name' => 'Chef Sukma',
                'email' => 'makanan@resto.id',
                'password' => 'password123',
                'role' => 'owner_makanan',
            ],
            [
                'username' => 'owner_minuman',
                'name' => 'Barista Akmal',
                'email' => 'minuman@resto.id',
                'password' => 'password123',
                'role' => 'owner_minuman',
            ],
            [
                'username' => 'admin_kasir',
                'name' => 'Kasir Utama / Admin',
                'email' => 'admin@resto.id',
                'password' => 'password123',
                'role' => 'admin',
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $definition) {
            User::updateOrCreate(
                ['username' => $definition['username']],
                [
                    'name' => $definition['name'],
                    'email' => $definition['email'],
                    'password' => $definition['password'],
                    'role' => $definition['role'],
                ]
            );
        }

        $this->command?->info('Akun demo (password: password123):');

        foreach (self::definitions() as $definition) {
            $this->command?->line("  - {$definition['username']} ({$definition['role']})");
        }
    }
}
