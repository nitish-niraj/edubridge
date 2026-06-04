<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table) {
            $table->boolean('earnings_released_email')->default(true)->after('review_received_email');
            $table->boolean('group_session_started_email')->default(true)->after('earnings_released_email');
        });
    }

    public function down(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table) {
            $table->dropColumn(['earnings_released_email', 'group_session_started_email']);
        });
    }
};
