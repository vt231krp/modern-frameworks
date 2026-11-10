<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id('attendance_id');
            $table->foreignId('member_id')
                ->constrained('members', 'member_id')
                ->cascadeOnDelete();
            $table->foreignId('class_id')
                ->constrained('classes', 'class_id')
                ->cascadeOnDelete();
            $table->string('status', 20)->default('present')->index();
            $table->timestamp('checked_in_at')->nullable();
            $table->string('notes')->nullable();
            $table->unique(['member_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
