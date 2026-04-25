<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('video_sessions')) {
            return;
        }

        Schema::table('video_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('video_sessions', 'conversation_id')) {
                $table->foreignId('conversation_id')
                    ->nullable()
                    ->after('booking_id')
                    ->constrained('conversations')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('video_sessions', 'is_group')) {
                $table->boolean('is_group')->default(false)->after('conversation_id');
            }

            if (! Schema::hasColumn('video_sessions', 'host_id')) {
                $table->foreignId('host_id')
                    ->nullable()
                    ->after('is_group')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('video_sessions')) {
            return;
        }

        Schema::table('video_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('video_sessions', 'host_id')) {
                $table->dropConstrainedForeignId('host_id');
            }

            if (Schema::hasColumn('video_sessions', 'is_group')) {
                $table->dropColumn('is_group');
            }

            if (Schema::hasColumn('video_sessions', 'conversation_id')) {
                $table->dropConstrainedForeignId('conversation_id');
            }
        });
    }
};
