<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the teacher_profiles table. Each row is owned by exactly one
     * user whose role is 'teacher'. Cascade delete ensures the profile is
     * removed when the parent user is deleted.
     */
    public function up(): void
    {
        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            // Biographical / professional information
            $table->text('bio')->nullable();
            $table->integer('experience_years')->default(0);

            // Pricing — nullable because a teacher may offer free sessions
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->boolean('is_free')->default(false);

            // Verification and quality signals
            $table->boolean('is_verified')->default(false);
            $table->decimal('rating_avg', 3, 2)->default(0.00);
            $table->integer('total_reviews')->default(0);

            // JSON arrays of subject names and spoken languages
            $table->json('subjects')->nullable();
            $table->json('languages')->nullable();

            $table->enum('gender', ['male', 'female', 'other'])->nullable();

            // Weekly availability schedule — JSON object keyed by day abbreviation
            // e.g. {"mon":{"on":true,"start":"09:00","end":"17:00"}, ...}
            $table->json('availability')->nullable();

            // Tracks how far the teacher has progressed through onboarding (0 = not started)
            $table->integer('onboarding_step')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_profiles');
    }
};
