<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'               => User::factory(),
            'class_grade'           => fake()->randomElement(['Class 9', 'Class 10', 'Class 11', 'Class 12', 'Undergraduate']),
            'school_name'           => fake()->company() . ' School',
            'subjects_needed'       => ['Math', 'Science'],
            'preferred_language'    => fake()->randomElement(['English', 'Hindi', 'Punjabi']),
            'onboarding_completed'  => false,
        ];
    }
}
