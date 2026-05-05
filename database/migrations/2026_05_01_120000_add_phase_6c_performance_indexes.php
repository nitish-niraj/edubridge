<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasIndex('teacher_availability', 'teacher_availability_teacher_id_is_active_day_of_week_index')) {
            Schema::table('teacher_availability', function (Blueprint $table): void {
                $table->index(['teacher_id', 'is_active', 'day_of_week']);
            });
        }

        if (! $this->hasIndex('teacher_availability', 'teacher_availability_teacher_id_specific_date_index')) {
            Schema::table('teacher_availability', function (Blueprint $table): void {
                $table->index(['teacher_id', 'specific_date']);
            });
        }

        if (! $this->hasIndex('teacher_earnings', 'teacher_earnings_teacher_id_status_payout_date_index')) {
            Schema::table('teacher_earnings', function (Blueprint $table): void {
                $table->index(['teacher_id', 'status', 'payout_date']);
            });
        }

        if (! $this->hasIndex('payments', 'payments_status_created_at_index')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->index(['status', 'created_at']);
            });
        }

        if (! $this->hasIndex('reviews', 'reviews_created_at_index')) {
            Schema::table('reviews', function (Blueprint $table): void {
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('teacher_availability', function (Blueprint $table): void {
            $table->dropIndex('teacher_availability_teacher_id_is_active_day_of_week_index');
            $table->dropIndex('teacher_availability_teacher_id_specific_date_index');
        });

        Schema::table('teacher_earnings', function (Blueprint $table): void {
            $table->dropIndex('teacher_earnings_teacher_id_status_payout_date_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_status_created_at_index');
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropIndex('reviews_created_at_index');
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
