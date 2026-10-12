<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categories')) {
            return;
        }

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('owner_type', ['makanan', 'minuman']);
            $table->string('icon', 50)->default('utensils');
            $table->timestamps();

            $table->index('owner_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
