<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Starter menu. Prices and opening stock are the intended initial values,
     * not the current (mutated) numbers.
     *
     * @return list<array{owner_type: string, name: string, description: string, price: string, stock: int, image: string}>
     */
    public static function definitions(): array
    {
        $unsplash = '?w=500&auto=format&fit=crop&q=60';

        return [
            // Makanan
            [
                'owner_type' => 'makanan',
                'name' => 'Nasi Goreng Spesial Resto',
                'description' => 'Nasi goreng racikan rempah dengan telur mata sapi, ayam suwir, dan kerupuk udang.',
                'price' => '28000.00',
                'stock' => 50,
                'image' => "https://images.unsplash.com/photo-1603133872878-684f208fb84b{$unsplash}",
            ],
            [
                'owner_type' => 'makanan',
                'name' => 'Ayam Bakar Madu Pedas',
                'description' => 'Potongan ayam bakar bumbu madu karamel dengan sambal terasi segar dan lalapan.',
                'price' => '35000.00',
                'stock' => 40,
                'image' => "https://images.unsplash.com/photo-1598515214211-89d3c73ae83b{$unsplash}",
            ],
            [
                'owner_type' => 'makanan',
                'name' => 'Mie Goreng Seafood Nusantara',
                'description' => 'Mie kuning kenyal dengan udang, cumi, bakso ikan, dan sayuran segar.',
                'price' => '32000.00',
                'stock' => 45,
                'image' => "https://images.unsplash.com/photo-1612927601601-6638404737ce{$unsplash}",
            ],
            [
                'owner_type' => 'makanan',
                'name' => 'Sop Buntut Gurih Rempah',
                'description' => 'Sop buntut sapi empuk berkuah kaldu bening gurih kaya rempah tradisional.',
                'price' => '48000.00',
                'stock' => 25,
                'image' => "https://images.unsplash.com/photo-1547928576-b258546ca337{$unsplash}",
            ],
            [
                'owner_type' => 'makanan',
                'name' => 'Kentang Goreng Truffle Crispy',
                'description' => 'French fries renyah dengan taburan truffle oil dan saus keju mayo.',
                'price' => '22000.00',
                'stock' => 60,
                'image' => "https://images.unsplash.com/photo-1576107232684-1279f3908594{$unsplash}",
            ],
            [
                'owner_type' => 'makanan',
                'name' => 'Tahu Cabe Garam Gurih',
                'description' => 'Tahu sutra krispi renyah ditumis bawang putih wangi dan cabai rawit pedas.',
                'price' => '18000.00',
                'stock' => 50,
                'image' => "https://images.unsplash.com/photo-1546069901-ba9599a7e63c{$unsplash}",
            ],
            [
                'owner_type' => 'makanan',
                'name' => 'Cireng Crispy Bumbu Rujak',
                'description' => 'Cireng kenyal kriuk disajikan dengan sambal rujak gula aren pedas manis.',
                'price' => '16000.00',
                'stock' => 70,
                'image' => "https://images.unsplash.com/photo-1565299624946-b28f40a0ae38{$unsplash}",
            ],

            // Minuman
            [
                'owner_type' => 'minuman',
                'name' => 'Es Kopi Susu Gula Aren',
                'description' => 'Espresso double shot dipadu susu segar creamy dan sirup gula aren murni.',
                'price' => '20000.00',
                'stock' => 100,
                'image' => "https://images.unsplash.com/photo-1517256064527-09c73fc73e38{$unsplash}",
            ],
            [
                'owner_type' => 'minuman',
                'name' => 'Caramel Macchiato Iced',
                'description' => 'Kopi susu dengan vanilla syrup dan drizzled saus karamel lezat.',
                'price' => '26000.00',
                'stock' => 80,
                'image' => "https://images.unsplash.com/photo-1578314675249-a6910f80cc4e{$unsplash}",
            ],
            [
                'owner_type' => 'minuman',
                'name' => 'Americano Cold Brew',
                'description' => 'Ekstrak espresso dingin segar dengan aroma fruity yang kaya.',
                'price' => '18000.00',
                'stock' => 90,
                'image' => "https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd{$unsplash}",
            ],
            [
                'owner_type' => 'minuman',
                'name' => 'Matcha Green Tea Latte',
                'description' => 'Bubuk matcha Uji premium dengan steamed milk manis lembut.',
                'price' => '25000.00',
                'stock' => 60,
                'image' => "https://images.unsplash.com/photo-1536256263959-770b48d82b0a{$unsplash}",
            ],
            [
                'owner_type' => 'minuman',
                'name' => 'Berry Sunset Sparkler Mocktail',
                'description' => 'Campuran sirup berry, soda dingin, lemon slices, dan daun mint segar.',
                'price' => '24000.00',
                'stock' => 75,
                'image' => "https://images.unsplash.com/photo-1551024709-8f23befc6f87{$unsplash}",
            ],
            [
                'owner_type' => 'minuman',
                'name' => 'Jus Alpukat Kerok Cokelat',
                'description' => 'Alpukat mentega kental dengan swirl kental manis cokelat premium.',
                'price' => '22000.00',
                'stock' => 50,
                'image' => "https://images.unsplash.com/photo-1613478223719-2ab802602423{$unsplash}",
            ],
            [
                'owner_type' => 'minuman',
                'name' => 'Jus Mangga Harum Manis',
                'description' => 'Jus mangga asli segar manis alami tanpa pemanis buatan.',
                'price' => '20000.00',
                'stock' => 50,
                'image' => "https://images.unsplash.com/photo-1546173159-315724a31d9b{$unsplash}",
            ],
        ];
    }

    public function run(): void
    {
        $categories = Category::whereIn('owner_type', ['makanan', 'minuman'])
            ->pluck('id', 'owner_type');

        foreach (self::definitions() as $definition) {
            Product::updateOrCreate(
                ['category_id' => $categories[$definition['owner_type']], 'name' => $definition['name']],
                [
                    'description' => $definition['description'],
                    'price' => $definition['price'],
                    'stock' => $definition['stock'],
                    'image' => $definition['image'],
                    'is_active' => true,
                ]
            );
        }
    }
}
