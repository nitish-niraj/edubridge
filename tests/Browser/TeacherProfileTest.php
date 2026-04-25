<?php

namespace Tests\Browser;

use App\Models\TeacherDocument;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class TeacherProfileTest extends DuskTestCase
{
    public function test_teacher_can_complete_profile(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $teacher = User::factory()->create([
            'name' => 'Profile Teacher',
            'email' => 'teacher.profile@example.com',
            'password' => Hash::make('Password@123'),
            'role' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        $profile = TeacherProfile::factory()->create([
            'user_id' => $teacher->id,
            'is_verified' => false,
            'subjects' => ['Math'],
            'languages' => ['English'],
        ]);

        foreach (['degree', 'service_record', 'id_proof'] as $type) {
            TeacherDocument::create([
                'teacher_id' => $profile->id,
                'type' => $type,
                'file_path' => 'tests/' . $type . '.pdf',
                'original_filename' => $type . '.pdf',
                'status' => 'approved',
                'uploaded_at' => now(),
            ]);
        }

        $this->browse(function (Browser $browser) use ($teacher): void {
            $browser->loginAs($teacher)
                ->visit('/teacher/profile/step/1')
                ->type('bio', 'Retired Math teacher with 30 years experience')
                ->type('experience_years', '30')
                ->type('previous_school', 'Government Senior Secondary School')
                ->press('Save & Continue →')
                ->assertPathIs('/teacher/profile/step/2')
                ->script([
                    "const subject = Array.from(document.querySelectorAll('div')).find((el) => el.textContent.includes('Math') && el.textContent.includes('Class')); if (subject) subject.click();",
                    "const language = Array.from(document.querySelectorAll('div')).find((el) => el.textContent.includes('English') && el.textContent.includes('Teach in English')); if (language) language.click();",
                ]);

            $browser->press('Save & Continue →')
                ->assertPathIs('/teacher/profile/step/3')
                ->click('.option-free-toggle')
                ->press('Save & Continue →')
                ->assertPathIs('/teacher/profile/step/4')
                ->press('Save & Continue →')
                ->assertPathIs('/teacher/profile/step/5')
                ->press('Submit for Verification ✓')
                ->assertPathIs('/teacher/dashboard')
                ->assertSee('Your profile is being reviewed');
        });
    }
}
