<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id('equipment_id');
            $table->string('name', 100);
            $table->integer('quantity')->default(1);
            $table->string('condition', 20)->default('good')->index();
            $table->foreignId('room_id')
                ->nullable()
                ->constrained('rooms', 'room_id')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
