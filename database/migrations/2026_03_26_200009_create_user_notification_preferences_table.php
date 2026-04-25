<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('new_message_email')->default(true);
            $table->boolean('new_message_sms')->default(true);
            $table->boolean('booking_confirmed_email')->default(true);
            $table->boolean('booking_confirmed_sms')->default(true);
            $table->boolean('session_reminder_email')->default(true);
            $table->boolean('session_reminder_sms')->default(true);
            $table->boolean('booking_cancelled_email')->default(true);
            $table->boolean('review_received_email')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
