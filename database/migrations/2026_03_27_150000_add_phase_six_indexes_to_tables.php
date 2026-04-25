<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasIndex('teacher_profiles', 'teacher_profiles_is_verified_rating_avg_index')) {
            Schema::table('teacher_profiles', function (Blueprint $table): void {
                $table->index(['is_verified', 'rating_avg']);
            });
        }

        if (! $this->hasIndex('bookings', 'bookings_student_id_status_index')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->index(['student_id', 'status']);
            });
        }

        if (! $this->hasIndex('bookings', 'bookings_teacher_id_status_index')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->index(['teacher_id', 'status']);
            });
        }

        if (! $this->hasIndex('bookings', 'bookings_start_at_index')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->index(['start_at']);
            });
        }

        if (! $this->hasIndex('messages', 'messages_conversation_id_created_at_index')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->index(['conversation_id', 'created_at']);
            });
        }

        if (! $this->hasIndex('reviews', 'reviews_reviewee_id_is_visible_index')) {
            Schema::table('reviews', function (Blueprint $table): void {
                $table->index(['reviewee_id', 'is_visible']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table): void {
            $table->dropIndex('teacher_profiles_is_verified_rating_avg_index');
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropIndex('reviews_reviewee_id_is_visible_index');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($index): bool => ($index->name ?? null) === $indexName),
            default => collect(DB::select("SHOW INDEX FROM `{$table}`"))
                ->contains(fn ($index): bool => ($index->Key_name ?? null) === $indexName),
        };
    }
};
