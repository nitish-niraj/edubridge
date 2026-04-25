<?php

namespace Tests\Browser;

use App\Models\BookingSlot;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class BookingFlowTest extends DuskTestCase
{
    public function test_student_can_book_free_session(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $student = User::factory()->create([
            'name' => 'Booking Student',
            'email' => 'booking.student@example.com',
            'password' => Hash::make('Password@123'),
            'role' => 'student',
            'status' => 'active',
        ]);
        $student->assignRole('student');

        $teacher = User::factory()->create([
            'name' => 'Free Session Teacher',
            'email' => 'booking.teacher@example.com',
            'password' => Hash::make('Password@123'),
            'role' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        TeacherProfile::factory()->create([
            'user_id' => $teacher->id,
            'is_verified' => true,
            'is_free' => true,
            'hourly_rate' => null,
            'subjects' => ['Math'],
            'languages' => ['English'],
        ]);

        BookingSlot::create([
            'teacher_id' => $teacher->id,
            'slot_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'is_booked' => false,
        ]);

        $this->browse(function (Browser $browser) use ($student, $teacher): void {
            $browser->loginAs($student)
                ->visit('/teachers/' . $teacher->id)
                ->press('📅 Book Session')
                ->waitFor('.booking-modal')
                ->click('.available-date')
                ->waitFor('.time-slot')
                ->click('.time-slot')
                ->press('Next')
                ->select('subject', 'Math')
                ->press('Next')
                ->assertSee('FREE')
                ->press('Confirm Booking')
                ->waitFor('.success-screen')
                ->assertSee('Booking Confirmed')
                ->press('Go to My Bookings')
                ->assertPathIs('/student/bookings')
                ->assertSee('Upcoming')
                ->assertSee($teacher->name);
        });
    }
}
