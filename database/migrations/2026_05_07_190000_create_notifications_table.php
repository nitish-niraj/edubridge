<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 20)->default('in_app');
            $table->string('audience', 20)->default('student');
            $table->string('type', 120);
            $table->string('title', 180)->nullable();
            $table->text('message');
            $table->json('data')->nullable();
            $table->boolean('is_critical')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'dismissed_at']);
            $table->index(['user_id', 'is_critical']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
