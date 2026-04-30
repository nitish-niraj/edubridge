<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('payer_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 8, 2);
            $table->integer('amount_paise');
            $table->decimal('platform_fee', 8, 2);
            $table->decimal('teacher_payout', 8, 2);
            $table->string('gateway', 20)->default('phonepe');
            $table->string('gateway_order_id', 100)->unique();
            $table->string('gateway_payment_id', 100)->nullable()->index();
            $table->enum('status', ['pending', 'held', 'released', 'refunded', 'failed'])
                  ->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
