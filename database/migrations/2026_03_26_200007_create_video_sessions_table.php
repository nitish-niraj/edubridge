<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->unique()->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->boolean('is_group')->default(false);
            $table->foreignId('host_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('room_name', 100);
            $table->string('room_type', 20)->default('peer-to-peer');
            $table->string('twilio_room_sid', 100)->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('recording_url', 500)->nullable();
            $table->string('composition_sid')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_sessions');
    }
};
