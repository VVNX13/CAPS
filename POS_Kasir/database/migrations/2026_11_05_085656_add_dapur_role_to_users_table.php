<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 'dapur' to the users.role ENUM.
     *
     * MySQL ENUM columns must be altered with a full column redefinition.
     */
    public function up(): void
    {
        // ALTER COLUMN is safer than raw SQL for cross-DB compat, but Laravel's
        // Blueprint doesn't let you change an existing enum without raw DDL.
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role`
            ENUM('owner_makanan','owner_minuman','admin','dapur') NOT NULL DEFAULT 'admin'");
    }

    public function down(): void
    {
        // If a dapur user exists, change their role before narrowing the enum.
        DB::statement("UPDATE `users` SET `role` = 'admin' WHERE `role` = 'dapur'");

        DB::statement("ALTER TABLE `users` MODIFY COLUMN `role`
            ENUM('owner_makanan','owner_minuman','admin') NOT NULL DEFAULT 'admin'");
    }
};
