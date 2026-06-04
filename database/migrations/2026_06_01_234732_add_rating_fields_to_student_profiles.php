<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->decimal('rating_avg', 3, 2)->default(0)->after('preferred_language');
            $table->unsignedInteger('total_reviews')->default(0)->after('rating_avg');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn(['rating_avg', 'total_reviews']);
        });
    }
};
