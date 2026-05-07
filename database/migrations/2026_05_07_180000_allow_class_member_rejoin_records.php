<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_members', function (Blueprint $table) {
            // Create a non-unique index first so the foreign key is satisfied
            $table->index('conversation_id');
            $table->dropUnique(['conversation_id', 'user_id']);
            $table->index(['conversation_id', 'user_id', 'left_at'], 'class_members_active_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('class_members', function (Blueprint $table) {
            $table->dropIndex('class_members_active_lookup_index');
            $table->unique(['conversation_id', 'user_id']);
        });
    }
};
