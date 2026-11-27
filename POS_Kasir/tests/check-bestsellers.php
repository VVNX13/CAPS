<?php

// Run: php tests/check-bestsellers.php (isolated in-memory database).
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
Illuminate\Support\Facades\DB::purge('sqlite');

use App\Http\Controllers\CustomerMenuController;
use Illuminate\Support\Facades\DB;

DB::statement('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, owner_type TEXT)');
DB::statement('CREATE TABLE products (id INTEGER PRIMARY KEY, category_id INTEGER, name TEXT, price NUMERIC, image TEXT, description TEXT, stock INTEGER, is_active INTEGER)');
DB::statement('CREATE TABLE sales (id INTEGER PRIMARY KEY, payment_status TEXT, order_status TEXT, created_at TEXT)');
DB::statement('CREATE TABLE sale_items (sale_id INTEGER, product_id INTEGER, quantity INTEGER)');

$check = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$controller = new CustomerMenuController;
$check($controller->index()->getData()['recommendations']->isEmpty(), 'Empty sales must not invent recommendations.');
DB::table('categories')->insert(['id' => 1, 'name' => 'Makanan', 'owner_type' => 'makanan']);
foreach (range(1, 6) as $id) {
    DB::table('products')->insert([
        'id' => $id, 'category_id' => 1, 'name' => "Menu $id", 'price' => 10000,
        'image' => null, 'description' => "Description $id", 'stock' => $id === 5 ? 0 : 10,
        'is_active' => $id === 6 ? 0 : 1,
    ]);
}
foreach ([
    [1, 'paid', 'pending', now()],
    [2, 'unpaid', 'pending', now()],
    [3, 'paid', 'canceled', now()],
    [4, 'paid', 'done', now()->subDays(31)],
] as [$id, $payment, $status, $date]) {
    DB::table('sales')->insert(['id' => $id, 'payment_status' => $payment, 'order_status' => $status, 'created_at' => $date]);
}
foreach ([[1, 1, 2], [1, 1, 3], [1, 2, 8], [1, 3, 5], [1, 4, 1], [1, 5, 999], [1, 6, 999], [2, 4, 100], [3, 4, 100], [4, 4, 100]] as [$sale, $product, $quantity]) {
    DB::table('sale_items')->insert(['sale_id' => $sale, 'product_id' => $product, 'quantity' => $quantity]);
}
$view = $controller->index();
$data = $view->getData();
$check($data['bestsellerIds'] === [2, 1, 3], 'Ranking must sum quantities, exclude unpaid/canceled/old/unavailable products, and break ties by ID.');
$check($data['recommendations']->pluck('quantity_sold')->all() === [8, 5, 5], 'Sales quantities must be real totals.');
$html = $view->render();
$check(str_contains($html, '8 porsi terjual'), 'View must render real sales.');
$check(! str_contains($html, '480x Terjual'), 'View must not render fabricated sales.');
echo "Bestseller checks passed.\n";
