<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('subject', 100)->nullable()->after('title');
            $table->text('description')->nullable()->after('subject');
            $table->unsignedInteger('max_students')->default(30)->after('description');
            $table->foreignId('teacher_id')->nullable()->after('max_students')->constrained('users')->nullOnDelete();
            $table->string('invite_code', 8)->unique()->nullable()->after('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->dropColumn(['subject', 'description', 'max_students', 'teacher_id', 'invite_code']);
        });
    }
};
