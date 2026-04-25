<?php

namespace Database\Seeders;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = [
            [
                'name'    => 'Dr. Rajinder Sharma',
                'email'   => 'rajinder.sharma@teacher.com',
                'gender'  => 'male',
                'subjects'=> ['Math', 'Physics'],
                'bio'     => 'Retired professor with 30 years of teaching Mathematics and Physics at higher secondary level.',
                'rate'    => 250,
                'rating'  => 4.8,
                'exp'     => 30,
            ],
            [
                'name'    => 'Mrs. Sunita Verma',
                'email'   => 'sunita.verma@teacher.com',
                'gender'  => 'female',
                'subjects'=> ['English', 'History'],
                'bio'     => 'Former school principal with expertise in English literature and Indian history.',
                'rate'    => 200,
                'rating'  => 4.6,
                'exp'     => 25,
            ],
            [
                'name'    => 'Mr. Harpreet Singh',
                'email'   => 'harpreet.singh@teacher.com',
                'gender'  => 'male',
                'subjects'=> ['Chemistry', 'Biology'],
                'bio'     => 'Ex-CBSE examiner with mastery in Chemistry and Biology for Class 11-12.',
                'rate'    => 300,
                'rating'  => 4.9,
                'exp'     => 28,
            ],
            [
                'name'    => 'Mrs. Kamala Devi',
                'email'   => 'kamala.devi@teacher.com',
                'gender'  => 'female',
                'subjects'=> ['Hindi', 'Geography'],
                'bio'     => 'Retired Hindi teacher with a passion for geography and regional languages.',
                'rate'    => 150,
                'rating'  => 4.5,
                'exp'     => 22,
            ],
            [
                'name'    => 'Mr. Ramesh Patel',
                'email'   => 'ramesh.patel@teacher.com',
                'gender'  => 'male',
                'subjects'=> ['Economics', 'Commerce'],
                'bio'     => 'Former college lecturer specialising in Economics and Business Studies.',
                'rate'    => 280,
                'rating'  => 4.7,
                'exp'     => 32,
            ],
            [
                'name'    => 'Mrs. Meena Krishnan',
                'email'   => 'meena.krishnan@teacher.com',
                'gender'  => 'female',
                'subjects'=> ['Math', 'Science'],
                'bio'     => 'Retired government school teacher; simplified math learning for thousands of students.',
                'rate'    => 180,
                'rating'  => 4.4,
                'exp'     => 20,
            ],
            [
                'name'    => 'Mr. Suresh Nair',
                'email'   => 'suresh.nair@teacher.com',
                'gender'  => 'male',
                'subjects'=> ['Computer Science', 'Math'],
                'bio'     => 'Ex-IT professional turned teacher, expertise in programming and mathematics.',
                'rate'    => null,
                'rating'  => 4.3,
                'exp'     => 15,
                'is_free' => true,
            ],
            [
                'name'    => 'Mrs. Anjali Gupta',
                'email'   => 'anjali.gupta@teacher.com',
                'gender'  => 'female',
                'subjects'=> ['Biology', 'Chemistry'],
                'bio'     => 'Retired biology teacher and NEET mentor with 26 years experience.',
                'rate'    => 320,
                'rating'  => 4.9,
                'exp'     => 26,
            ],
            [
                'name'    => 'Mr. Balvinder Kaur',
                'email'   => 'balvinder.kaur@teacher.com',
                'gender'  => 'male',
                'subjects'=> ['Punjabi', 'Hindi'],
                'bio'     => 'Language expert with experience teaching Punjabi and Hindi at university level.',
                'rate'    => 160,
                'rating'  => 3.9,
                'exp'     => 18,
            ],
            [
                'name'    => 'Mrs. Lakshmi Rao',
                'email'   => 'lakshmi.rao@teacher.com',
                'gender'  => 'female',
                'subjects'=> ['Physics', 'Math'],
                'bio'     => 'IIT graduate turned educator; makes Physics fun and accessible for JEE aspirants.',
                'rate'    => 400,
                'rating'  => 5.0,
                'exp'     => 20,
            ],
        ];

        foreach ($teachers as $t) {
            $user = User::firstOrCreate(
                ['email' => $t['email']],
                [
                    'name'              => $t['name'],
                    'password'          => Hash::make('Teacher@123'),
                    'role'              => 'teacher',
                    'status'            => 'active',
                    'email_verified_at' => now(),
                ]
            );

            $user->assignRole('teacher');

            TeacherProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'bio'            => $t['bio'],
                    'experience_years'=> $t['exp'],
                    'previous_school'=> 'Retired Faculty - ' . ($t['subjects'][0] ?? 'General Studies') . ' Department',
                    'hourly_rate'    => $t['rate'] ?? null,
                    'is_free'        => $t['is_free'] ?? false,
                    'is_verified'    => true,
                    'rating_avg'     => $t['rating'],
                    'total_reviews'  => rand(5, 50),
                    'subjects'       => $t['subjects'],
                    'languages'      => ['English', 'Hindi'],
                    'gender'         => $t['gender'],
                ]
            );
        }
    }
}
