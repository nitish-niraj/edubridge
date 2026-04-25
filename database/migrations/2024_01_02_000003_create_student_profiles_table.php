<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the student_profiles table. Each row is owned by exactly one
     * user whose role is 'student'. Cascade delete ensures the profile is
     * removed when the parent user is deleted.
     */
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            // Academic context
            $table->string('class_grade', 20)->nullable();
            $table->string('school_name', 150)->nullable();

            // JSON array of subjects the student needs help with
            $table->json('subjects_needed')->nullable();

            // The language the student prefers to be taught in
            $table->string('preferred_language', 50)->nullable();

            // Set to true once the student has finished the onboarding flow
            $table->boolean('onboarding_completed')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
