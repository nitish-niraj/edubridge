<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reviews', 'is_flagged')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->boolean('is_flagged')->default(false)->after('comment');
            });
        }

        if (! Schema::hasColumn('reviews', 'is_visible')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->boolean('is_visible')->default(true)->after('comment');
            });
        }
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'is_flagged')) {
                $table->dropColumn('is_flagged');
            }

            if (Schema::hasColumn('reviews', 'is_visible')) {
                $table->dropColumn('is_visible');
            }
        });
    }
};
