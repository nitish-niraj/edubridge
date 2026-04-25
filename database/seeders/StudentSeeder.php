<?php

namespace Database\Seeders;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $students = [
            [
                'name'        => 'Priya Mehta',
                'email'       => 'priya.mehta@student.com',
                'class_grade' => 'Class 11',
                'school_name' => 'Delhi Public School, Amritsar',
                'subjects'    => ['Math', 'Physics', 'Chemistry'],
                'language'    => 'Hindi',
            ],
            [
                'name'        => 'Arjun Kumar',
                'email'       => 'arjun.kumar@student.com',
                'class_grade' => 'Class 10',
                'school_name' => 'St. Xavier\'s School, Mumbai',
                'subjects'    => ['Math', 'Science', 'English'],
                'language'    => 'English',
            ],
            [
                'name'        => 'Neha Sharma',
                'email'       => 'neha.sharma@student.com',
                'class_grade' => 'Undergraduate',
                'school_name' => 'Punjab University, Chandigarh',
                'subjects'    => ['Economics', 'Commerce'],
                'language'    => 'Punjabi',
            ],
            [
                'name'        => 'Rahul Singh',
                'email'       => 'rahul.singh@student.com',
                'class_grade' => 'Class 12',
                'school_name' => 'Kendriya Vidyalaya, Bangalore',
                'subjects'    => ['Biology', 'Chemistry'],
                'language'    => 'English',
            ],
            [
                'name'        => 'Ananya Patel',
                'email'       => 'ananya.patel@student.com',
                'class_grade' => 'Class 9',
                'school_name' => 'Ahmedabad International School',
                'subjects'    => ['Math', 'Hindi', 'Computer Science'],
                'language'    => 'Gujarati',
            ],
        ];

        foreach ($students as $s) {
            $user = User::firstOrCreate(
                ['email' => $s['email']],
                [
                    'name'              => $s['name'],
                    'password'          => Hash::make('Student@123'),
                    'role'              => 'student',
                    'status'            => 'active',
                    'email_verified_at' => now(),
                ]
            );

            $user->assignRole('student');

            StudentProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'class_grade'           => $s['class_grade'],
                    'school_name'           => $s['school_name'],
                    'subjects_needed'       => $s['subjects'],
                    'preferred_language'    => $s['language'],
                    'onboarding_completed'  => true,
                ]
            );
        }
    }
}
