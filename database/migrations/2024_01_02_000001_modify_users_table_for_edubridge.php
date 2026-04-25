<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds EduBridge-specific columns to the existing Laravel users table.
     * The base users table (id, name, email, email_verified_at, password,
     * remember_token, timestamps) is already created by the default Laravel
     * migration at 2014_10_12_000000_create_users_table.php.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Phone number — nullable and unique, placed after email
            $table->string('phone')->nullable()->unique()->after('email');

            // Role determines which profile type the user belongs to
            $table->enum('role', ['student', 'teacher', 'admin'])->after('password');

            // Timestamp for when the phone number was verified
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');

            // Optional profile avatar path
            $table->string('avatar')->nullable()->after('role');

            // Account status; new accounts start as 'pending' until approved/verified
            $table->enum('status', ['active', 'suspended', 'pending'])
                ->default('pending')
                ->after('avatar');

            // Soft-delete support — adds deleted_at column
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Removes all columns that were added by this migration,
     * restoring the users table to its base Laravel state.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the unique index on phone before dropping the column
            $table->dropUnique(['phone']);
            $table->dropColumn([
                'phone',
                'role',
                'phone_verified_at',
                'avatar',
                'status',
                'deleted_at',
            ]);
        });
    }
};
