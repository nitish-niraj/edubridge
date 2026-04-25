<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('teacher_profiles', 'tour_completed')) {
            Schema::table('teacher_profiles', function (Blueprint $table): void {
                $table->boolean('tour_completed')->default(false)->after('onboarding_step');
            });
        }

        if (! Schema::hasColumn('users', 'two_factor_secret')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->text('two_factor_secret')->nullable()->after('city');
            });
        }

        if (! Schema::hasColumn('users', 'two_factor_enabled')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('two_factor_enabled')->default(false)->after('two_factor_secret');
            });
        }
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('teacher_profiles', 'tour_completed')) {
                $table->dropColumn('tour_completed');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'two_factor_enabled')) {
                $table->dropColumn('two_factor_enabled');
            }

            if (Schema::hasColumn('users', 'two_factor_secret')) {
                $table->dropColumn('two_factor_secret');
            }
        });
    }
};
