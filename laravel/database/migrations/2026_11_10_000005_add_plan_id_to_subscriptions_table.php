<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('member_id');
            $table->index('plan_id');
        });

        // SQLite cannot add a foreign key to an existing table, so enforce it
        // only on databases that support it (MySQL/MariaDB/PostgreSQL).
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->foreign('plan_id')
                    ->references('plan_id')
                    ->on('membership_plans')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropForeign(['plan_id']);
            });
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['plan_id']);
            $table->dropColumn('plan_id');
        });
    }
};
