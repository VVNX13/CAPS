<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // cashier
            $table->decimal('opening_amount', 10, 2);
            $table->decimal('total_cash_sales', 10, 2)->default(0); // sum of cash sales during shift
            $table->decimal('closing_amount', 10, 2)->nullable(); // physical cash counted at close
            $table->decimal('expected_amount', 10, 2)->nullable(); // opening + cash sales
            $table->decimal('discrepancy', 10, 2)->nullable(); // actual - expected
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_shifts');
    }
};
