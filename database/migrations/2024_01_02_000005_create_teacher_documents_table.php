<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the teacher_documents table. Documents belong to a
     * teacher_profiles row and are reviewed by admin users.
     * Deleting a teacher profile cascades and removes all associated documents.
     */
    public function up(): void
    {
        Schema::create('teacher_documents', function (Blueprint $table) {
            $table->id();

            // Owning teacher profile
            $table->unsignedBigInteger('teacher_id');
            $table->foreign('teacher_id')
                ->references('id')
                ->on('teacher_profiles')
                ->cascadeOnDelete();

            // Category of the uploaded document
            $table->enum('type', ['degree', 'service_record', 'id_proof', 'other']);

            // Storage path returned by the filesystem (e.g. Storage::put)
            $table->string('file_path');

            // Original client-side filename for display purposes
            $table->string('original_filename');

            // Review lifecycle status
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // Filled in by the reviewer when a document is rejected
            $table->text('rejection_reason')->nullable();

            // The admin user who performed the review (nullable — not yet reviewed)
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // When the review action took place
            $table->timestamp('reviewed_at')->nullable();

            // Explicit upload timestamp (separate from created_at for clarity)
            $table->timestamp('uploaded_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_documents');
    }
};
