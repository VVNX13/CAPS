<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RecommendationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::where('is_active', true)->where('stock', '>', 0)->orderBy('id')->limit(3)->get();

        if ($products->count() < 3) {
            throw new RuntimeException('Diperlukan minimal 3 menu aktif dengan stok tersedia.');
        }

        $saleColumns = array_flip(Schema::getColumnListing('sales'));

        DB::transaction(function () use ($products, $saleColumns) {
            foreach ($products as $index => $product) {
                $quantity = [12, 8, 5][$index];
                $total = (float) $product->price * $quantity;
                $invoice = 'DUMMY-REC-'.($index + 1);
                $now = now();

                DB::table('sales')->updateOrInsert(['invoice_number' => $invoice], array_intersect_key([
                    'customer_name' => 'Dummy Rekomendasi '.($index + 1),
                    'customer_phone' => '080000000000',
                    'service_type' => 'takeaway',
                    'payment_method' => 'cash',
                    'payment_status' => 'paid',
                    'order_status' => 'completed',
                    'subtotal_amount' => $total,
                    'discount_amount' => 0,
                    'total_amount' => $total,
                    'cash_received' => $total,
                    'cash_change' => 0,
                    'notes' => 'DUMMY-REC: data demonstrasi rekomendasi, bukan penjualan nyata.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $saleColumns));

                $saleId = DB::table('sales')->where('invoice_number', $invoice)->value('id');
                DB::table('sale_items')->where('sale_id', $saleId)->delete();
                DB::table('sale_items')->insert([
                    'sale_id' => $saleId,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'subtotal' => $total,
                    'item_status' => 'done',
                    'notes' => 'DUMMY-REC',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        foreach ($products as $index => $product) {
            $this->command->info($product->name.': '.[12, 8, 5][$index].' porsi dummy');
        }
    }
}
