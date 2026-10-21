<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sale_items')) {
            return;
        }

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->enum('item_status', ['pending', 'cooking', 'done'])->default('pending');
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['sale_id', 'item_status']);
            $table->index('item_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
