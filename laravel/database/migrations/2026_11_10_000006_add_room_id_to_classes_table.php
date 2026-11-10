<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable()->after('coach_id');
            $table->index('room_id');
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('classes', function (Blueprint $table) {
                $table->foreign('room_id')
                    ->references('room_id')
                    ->on('rooms')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('classes', function (Blueprint $table) {
                $table->dropForeign(['room_id']);
            });
        }

        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex(['room_id']);
            $table->dropColumn('room_id');
        });
    }
};
