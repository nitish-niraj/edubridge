<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('video_sessions', 'booking_id')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE video_sessions MODIFY booking_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('video_sessions', 'booking_id')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE video_sessions MODIFY booking_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
