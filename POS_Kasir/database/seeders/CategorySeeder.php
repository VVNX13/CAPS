<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * The POS splits ownership in two: food and drinks.
     *
     * @return list<array{name: string, owner_type: string, icon: string}>
     */
    public static function definitions(): array
    {
        return [
            ['name' => 'Makanan', 'owner_type' => 'makanan', 'icon' => 'bowl-food'],
            ['name' => 'Minuman', 'owner_type' => 'minuman', 'icon' => 'mug-hot'],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $definition) {
            Category::updateOrCreate(
                ['owner_type' => $definition['owner_type']],
                $definition
            );
        }
    }
}
