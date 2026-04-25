<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class StudentJourneyTest extends DuskTestCase
{
    public function test_student_can_find_and_message_teacher(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $this->browse(function (Browser $browser): void {
            $browser->visit('/register/student')
                ->type('name', 'Test Student')
                ->type('email', 'teststudent@example.com')
                ->type('phone', '9000000001')
                ->select('class_grade', 'Class 12')
                ->type('password', 'Password@123')
                ->type('password_confirmation', 'Password@123')
                ->press('Create Account 🚀')
                ->assertPathIs('/verify-otp')
                ->visit('/test-verify-otp?email=teststudent@example.com')
                ->assertPathIs('/student/onboarding')
                ->press('Skip')
                ->assertPathIs('/student/dashboard')
                ->clickLink('Find Teachers')
                ->assertPathIs('/teachers')
                ->type('search', 'Math')
                ->waitFor('.teacher-card')
                ->click('.teacher-card:first-child .view-profile-btn')
                ->assertSee('Book Session')
                ->assertSee('Send Message');
        });
    }
}
