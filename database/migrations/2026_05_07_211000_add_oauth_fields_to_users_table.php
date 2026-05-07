<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('oauth_provider', 30)->nullable()->after('password');
            $table->string('oauth_provider_id', 120)->nullable()->after('oauth_provider');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['oauth_provider', 'oauth_provider_id']);
        });
    }
};

