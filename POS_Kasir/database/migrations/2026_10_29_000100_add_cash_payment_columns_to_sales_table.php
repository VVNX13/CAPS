<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uang yang diterima kasir dan kembaliannya, untuk dicetak di struk.
 * Hanya terisi untuk pembayaran tunai yang dikonfirmasi lewat kalkulator kembalian.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales') && ! Schema::hasColumn('sales', 'cash_received')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->decimal('cash_received', 12, 2)->nullable()->after('payment_status');
                $table->decimal('cash_change', 12, 2)->nullable()->after('cash_received');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'cash_received')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn(['cash_received', 'cash_change']);
            });
        }
    }
};
