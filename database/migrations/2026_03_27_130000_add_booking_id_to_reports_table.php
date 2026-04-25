<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (! Schema::hasColumn('reports', 'booking_id')) {
                $table->foreignId('booking_id')
                    ->nullable()
                    ->after('reported_review_id')
                    ->constrained('bookings')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (Schema::hasColumn('reports', 'booking_id')) {
                $table->dropConstrainedForeignId('booking_id');
            }
        });
    }
};
