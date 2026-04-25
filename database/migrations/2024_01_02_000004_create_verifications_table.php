<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the verifications table used for OTP-based email and phone
     * verification. The OTP value stored in the 'otp' column must always be
     * hashed (e.g. bcrypt / Hash::make) before persisting — never plain text.
     * Cascade delete removes pending tokens when the user is deleted.
     */
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            // Hashed OTP value — never store plain-text codes
            $table->string('otp');

            // Distinguishes between email and SMS/phone OTP flows
            $table->enum('type', ['email', 'phone']);

            // Hard expiry; application logic should reject expired tokens
            $table->timestamp('expires_at');

            // Null means the token has not been consumed yet
            $table->timestamp('used_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};
