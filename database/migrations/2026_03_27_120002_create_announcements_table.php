<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('message');
            $table->enum('target_role', ['all', 'student', 'teacher'])->default('all');
            $table->enum('delivery_type', ['banner', 'email', 'both'])->default('banner');
            $table->boolean('is_active')->default(true);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
