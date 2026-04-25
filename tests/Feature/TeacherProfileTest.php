<?php

namespace Tests\Feature;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['student', 'teacher', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function makeTeacher(): User
    {
        $user = User::factory()->create([
            'email'             => 'prof@test.com',
            'password'          => Hash::make('Password@123'),
            'role'              => 'teacher',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('teacher');
        TeacherProfile::factory()->create(['user_id' => $user->id]);
        return $user;
    }

    public function test_step1_saves_bio_and_experience(): void
    {
        $user = $this->makeTeacher();

        $response = $this->actingAs($user)->post(route('teacher.profile.step1.save'), [
            'bio'              => 'I am an experienced teacher with 30 years of teaching.',
            'experience_years' => 30,
            'previous_school'  => 'Government High School, Amritsar',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('teacher_profiles', [
            'user_id'          => $user->id,
            'experience_years' => 30,
        ]);

        $profile = TeacherProfile::where('user_id', $user->id)->first();
        $this->assertEquals('I am an experienced teacher with 30 years of teaching.', $profile->bio);
    }

    public function test_step2_saves_subjects_and_languages(): void
    {
        $user = $this->makeTeacher();

        $response = $this->actingAs($user)->post(route('teacher.profile.step2.save'), [
            'subjects'  => ['Math', 'Physics'],
            'languages' => ['English', 'Hindi'],
        ]);

        $response->assertRedirect();

        $profile = TeacherProfile::where('user_id', $user->id)->first();
        $this->assertContains('Math', $profile->subjects);
        $this->assertContains('English', $profile->languages);
    }

    public function test_step3_saves_rate(): void
    {
        $user = $this->makeTeacher();

        $response = $this->actingAs($user)->post(route('teacher.profile.step3.save'), [
            'is_free'     => false,
            'hourly_rate' => 250,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('teacher_profiles', [
            'user_id'     => $user->id,
            'hourly_rate' => 250,
            'is_free'     => false,
        ]);
    }

    public function test_step3_saves_free_option(): void
    {
        $user = $this->makeTeacher();

        $response = $this->actingAs($user)->post(route('teacher.profile.step3.save'), [
            'is_free'     => true,
            'hourly_rate' => null,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('teacher_profiles', [
            'user_id' => $user->id,
            'is_free' => true,
        ]);
    }

    public function test_step4_saves_availability(): void
    {
        $user = $this->makeTeacher();

        $response = $this->actingAs($user)->post(route('teacher.profile.step4.save'), [
            'availability' => [
                'Monday' => ['enabled' => true, 'start' => '09:00', 'end' => '12:00'],
                'Tuesday' => ['enabled' => false, 'start' => null, 'end' => null],
                'Wednesday' => ['enabled' => true, 'start' => '10:00', 'end' => '14:00'],
                'Thursday' => ['enabled' => false, 'start' => null, 'end' => null],
                'Friday' => ['enabled' => true, 'start' => '11:00', 'end' => '15:00'],
                'Saturday' => ['enabled' => false, 'start' => null, 'end' => null],
                'Sunday' => ['enabled' => false, 'start' => null, 'end' => null],
            ],
        ]);

        $response->assertRedirect();

        $profile = TeacherProfile::where('user_id', $user->id)->first();
        $this->assertNotNull($profile);
        $this->assertTrue($profile->availability['Monday']['enabled']);
        $this->assertSame('09:00', $profile->availability['Monday']['start']);
    }

    public function test_step5_uploads_avatar_and_documents(): void
    {
        Storage::fake('public');
        $user = $this->makeTeacher();
        $response = $this->actingAs($user)->post(route('teacher.profile.step5.save'), [
            'avatar' => UploadedFile::fake()->createWithContent('avatar.jpg', base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=')),
            'degree' => UploadedFile::fake()->createWithContent('degree.pdf', '%PDF-1.4 mock content'),
            'service_record' => UploadedFile::fake()->createWithContent('service_record.pdf', '%PDF-1.4 mock content'),
            'id_proof' => UploadedFile::fake()->createWithContent('id_proof.pdf', '%PDF-1.4 mock content'),
        ]);

        if ($response->status() !== 302 || $response->headers->get('Location') !== route('teacher.dashboard')) {
            $response->dumpSession();
            $response->dump();
        }
        $response->assertRedirect(route('teacher.dashboard'));

        $profile = TeacherProfile::where('user_id', $user->id)->first();
        $this->assertNotNull($profile);

        $this->assertDatabaseHas('teacher_documents', [
            'teacher_id' => $profile->id,
            'type' => 'degree',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('teacher_documents', [
            'teacher_id' => $profile->id,
            'type' => 'service_record',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('teacher_documents', [
            'teacher_id' => $profile->id,
            'type' => 'id_proof',
            'status' => 'pending',
        ]);
    }
}
