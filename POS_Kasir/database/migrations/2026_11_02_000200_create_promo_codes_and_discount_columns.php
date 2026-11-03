<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Potongan harga: kupon promo + kolom diskon di transaksi.
 *
 * sales.subtotal_amount = total sebelum diskon,
 * sales.total_amount    = yang benar-benar ditagih (subtotal - diskon).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promo_codes')) {
            Schema::create('promo_codes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('name', 100);
                $table->enum('type', ['percent', 'fixed']);
                $table->decimal('value', 12, 2);
                $table->decimal('min_order', 12, 2)->default(0);
                $table->decimal('max_discount', 12, 2)->nullable();
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->date('starts_at')->nullable();
                $table->date('ends_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('sales') && ! Schema::hasColumn('sales', 'discount_amount')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->decimal('subtotal_amount', 12, 2)->nullable()->after('table_number');
                $table->decimal('discount_amount', 12, 2)->default(0)->after('subtotal_amount');
                $table->string('promo_code', 30)->nullable()->after('discount_amount');
                $table->string('discount_note', 255)->nullable()->after('promo_code');
            });

            // Transaksi lama belum pernah diberi diskon.
            DB::table('sales')->whereNull('subtotal_amount')->update([
                'subtotal_amount' => DB::raw('total_amount'),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'discount_amount')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn(['subtotal_amount', 'discount_amount', 'promo_code', 'discount_note']);
            });
        }

        Schema::dropIfExists('promo_codes');
    }
};
