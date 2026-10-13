<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id('sub_id');
            $table->foreignId('member_id')->constrained('members', 'member_id')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('price', 10, 2)->nullable();
        });

        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT check_end_date CHECK (end_date >= start_date)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
