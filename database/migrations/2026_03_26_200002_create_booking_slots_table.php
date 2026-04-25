<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->date('slot_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes')->default(60);
            $table->boolean('is_booked')->default(false);
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'slot_date', 'is_booked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_slots');
    }
};
