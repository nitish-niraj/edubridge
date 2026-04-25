<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained('booking_slots')->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'])
                  ->default('pending');
            $table->enum('session_type', ['solo', 'group'])->default('solo');
            $table->string('subject', 100)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('price', 8, 2)->default(0.00);
            $table->decimal('platform_fee', 8, 2)->default(0.00);
            $table->decimal('teacher_payout', 8, 2)->default(0.00);
            $table->enum('payment_status', ['unpaid', 'paid', 'held', 'released', 'refunded'])
                  ->default('unpaid');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'status']);
            $table->index(['teacher_id', 'status']);
            $table->index(['start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
