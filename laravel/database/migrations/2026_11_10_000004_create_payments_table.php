<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->foreignId('sub_id')
                ->constrained('subscriptions', 'sub_id')
                ->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('method', 20)->default('card')->index();
            $table->string('status', 20)->default('pending')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
