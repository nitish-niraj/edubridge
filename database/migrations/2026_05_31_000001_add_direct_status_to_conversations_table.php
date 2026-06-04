<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('direct_status', 20)->default('accepted')->after('teacher_id');
            $table->timestamp('accepted_at')->nullable()->after('direct_status');
            $table->timestamp('declined_at')->nullable()->after('accepted_at');
            $table->index(['direct_status', 'is_group']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex(['direct_status', 'is_group']);
            $table->dropColumn(['direct_status', 'accepted_at', 'declined_at']);
        });
    }
};
