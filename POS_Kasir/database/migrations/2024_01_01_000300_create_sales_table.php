<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales')) {
            return;
        }

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->string('customer_name', 100);
            $table->string('customer_phone', 25);
            $table->string('customer_email', 100)->nullable();
            $table->enum('service_type', ['dine_in', 'takeaway']);
            $table->string('table_number', 10)->nullable();
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->decimal('qris_amount_total', 12, 2)->nullable();
            $table->enum('payment_method', ['qris', 'cash']);
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->text('qris_payload')->nullable();
            $table->string('qris_transaction_id', 100)->nullable();
            $table->enum('order_status', ['pending', 'processing', 'completed', 'canceled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('payment_status');
            $table->index('order_status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
