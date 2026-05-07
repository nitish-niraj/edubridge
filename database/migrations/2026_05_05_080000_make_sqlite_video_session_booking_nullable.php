<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite' || ! Schema::hasTable('video_sessions')) {
            return;
        }

        if ($this->bookingIdIsNullable()) {
            return;
        }

        DB::statement('PRAGMA foreign_keys=OFF');

        try {
            DB::statement(<<<SQL
CREATE TABLE video_sessions_new (
    id integer primary key autoincrement not null,
    booking_id integer,
    conversation_id integer,
    is_group tinyint(1) not null default '0',
    host_id integer,
    room_name varchar not null,
    room_type varchar not null default 'peer-to-peer',
    twilio_room_sid varchar,
    started_at datetime,
    ended_at datetime,
    duration_minutes integer,
    recording_url varchar,
    composition_sid varchar,
    created_at datetime,
    updated_at datetime,
    foreign key(booking_id) references bookings(id) on delete cascade,
    foreign key(conversation_id) references conversations(id) on delete set null,
    foreign key(host_id) references users(id) on delete set null
)
SQL);

            DB::statement(<<<SQL
INSERT INTO video_sessions_new (
    id, booking_id, conversation_id, is_group, host_id, room_name, room_type,
    twilio_room_sid, started_at, ended_at, duration_minutes, recording_url,
    composition_sid, created_at, updated_at
)
SELECT
    id, booking_id, conversation_id, is_group, host_id, room_name, room_type,
    twilio_room_sid, started_at, ended_at, duration_minutes, recording_url,
    composition_sid, created_at, updated_at
FROM video_sessions
SQL);

            DB::statement('DROP TABLE video_sessions');
            DB::statement('ALTER TABLE video_sessions_new RENAME TO video_sessions');
            DB::statement('CREATE UNIQUE INDEX video_sessions_booking_id_unique ON video_sessions (booking_id)');
        } finally {
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    public function down(): void
    {
        // Keep this migration irreversible for SQLite: existing group sessions can have NULL booking_id.
    }

    private function bookingIdIsNullable(): bool
    {
        $column = collect(DB::select('PRAGMA table_info(video_sessions)'))
            ->firstWhere('name', 'booking_id');

        return $column && (int) $column->notnull === 0;
    }
};
