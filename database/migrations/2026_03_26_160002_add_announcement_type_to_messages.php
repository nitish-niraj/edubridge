<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Messages now store the type as a portable string, so no schema
        // alteration is needed for the announcement variant.
    }

    public function down(): void
    {
        // No-op for the same reason as up().
    }
};
