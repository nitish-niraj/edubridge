<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->enum('day_of_week', [
                'monday', 'tuesday', 'wednesday', 'thursday',
                'friday', 'saturday', 'sunday',
            ])->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_recurring')->default(true);
            $table->date('specific_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_availability');
    }
};
