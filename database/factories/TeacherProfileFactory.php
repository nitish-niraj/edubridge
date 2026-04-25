<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeacherProfile>
 */
class TeacherProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'bio'              => fake()->paragraph(),
            'experience_years' => fake()->numberBetween(1, 40),
            'previous_school'  => fake()->company() . ', ' . fake()->city(),
            'hourly_rate'      => fake()->randomElement([null, 150, 200, 250, 300, 400]),
            'is_free'          => false,
            'is_verified'      => false,
            'rating_avg'       => fake()->randomFloat(2, 3.0, 5.0),
            'total_reviews'    => fake()->numberBetween(0, 50),
            'subjects'         => ['Math', 'Science'],
            'languages'        => ['English', 'Hindi'],
            'gender'           => fake()->randomElement(['male', 'female', 'other']),
        ];
    }
}
