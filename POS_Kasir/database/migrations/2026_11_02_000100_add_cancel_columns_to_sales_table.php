<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak audit pembatalan pesanan (void order): kapan, oleh siapa, dan alasannya.
 * Status batalnya sendiri memakai kolom order_status = 'canceled' yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales') && ! Schema::hasColumn('sales', 'canceled_at')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->timestamp('canceled_at')->nullable()->after('order_status');
                $table->foreignId('canceled_by')->nullable()->after('canceled_at')
                    ->constrained('users')->nullOnDelete();
                $table->string('cancel_reason', 255)->nullable()->after('canceled_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'canceled_at')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropConstrainedForeignId('canceled_by');
                $table->dropColumn(['canceled_at', 'cancel_reason']);
            });
        }
    }
};
