<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('direct_student_id')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->unique(['direct_student_id', 'teacher_id'], 'conversations_direct_pair_unique');
            $table->index('is_group');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique('conversations_direct_pair_unique');
            $table->dropIndex(['is_group']);
            $table->dropForeign(['direct_student_id']);
            $table->dropColumn('direct_student_id');
        });
    }
};
