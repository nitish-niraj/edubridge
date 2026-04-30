<?php

namespace Tests\Feature;

use App\Models\BookingSlot;
use App\Models\TeacherAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_availability()
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        Sanctum::actingAs($teacher);

        $response = $this->postJson('/api/teacher/availability', [
            'day_of_week' => 'monday',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_recurring' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('teacher_availability', [
            'teacher_id' => $teacher->id,
            'day_of_week' => 'monday',
        ]);
        
        // Ensure slots were generated
        $this->assertTrue(BookingSlot::where('teacher_id', $teacher->id)->exists());
    }

    public function test_teacher_cannot_create_overlapping_availability()
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        Sanctum::actingAs($teacher);

        TeacherAvailability::create([
            'teacher_id' => $teacher->id,
            'day_of_week' => 'monday',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_recurring' => true,
        ]);

        $response = $this->postJson('/api/teacher/availability', [
            'day_of_week' => 'monday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'is_recurring' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_generate_booking_slots_command_handles_specific_dates()
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        
        $date = Carbon::tomorrow()->toDateString();

        TeacherAvailability::create([
            'teacher_id' => $teacher->id,
            'start_time' => '14:00',
            'end_time' => '15:00',
            'is_recurring' => false,
            'specific_date' => $date,
        ]);

        $this->artisan('slots:generate')->assertSuccessful();

        $this->assertDatabaseHas('booking_slots', [
            'teacher_id' => $teacher->id,
        ]);
        
        // Should only be one slot
        $this->assertEquals(1, BookingSlot::count());
    }
}
