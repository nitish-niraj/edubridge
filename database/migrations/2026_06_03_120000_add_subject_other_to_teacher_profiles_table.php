<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §3.3: "Other (allows a short free-text specification, max 50 chars)".
     * Adds a nullable string column to capture the free-text subject specification
     * when a teacher selects "Other" from the canonical subjects list.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('teacher_profiles', 'subject_other')) {
            Schema::table('teacher_profiles', function (Blueprint $table): void {
                $table->string('subject_other', 50)->nullable()->after('subjects');
            });
        }
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('teacher_profiles', 'subject_other')) {
                $table->dropColumn('subject_other');
            }
        });
    }
};
