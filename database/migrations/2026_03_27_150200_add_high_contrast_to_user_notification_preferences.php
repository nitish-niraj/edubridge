<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_notification_preferences', 'high_contrast')) {
            Schema::table('user_notification_preferences', function (Blueprint $table): void {
                $table->boolean('high_contrast')->default(false)->after('review_received_email');
            });
        }
    }

    public function down(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table): void {
            if (Schema::hasColumn('user_notification_preferences', 'high_contrast')) {
                $table->dropColumn('high_contrast');
            }
        });
    }
};
