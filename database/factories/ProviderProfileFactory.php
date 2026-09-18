<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderProfileFactory extends Factory
{
    public array $titleToCategory = [
        'Plumber'                => 'Home Services',
        'Electrician'             => 'Home Services',
        'House Painter'          => 'Home Services',
        'Carpenter'               => 'Home Services',
        'Auto Mechanic'           => 'Automotive',
        'Mobile Car Wash Operator'=> 'Automotive',
        'House Cleaner'           => 'Cleaning',
        'Office Cleaner'          => 'Cleaning',
        'Math Tutor'              => 'Education & Training',
        'English Tutor'           => 'Education & Training',
        'Music Teacher'           => 'Education & Training',
        'Hairdresser'             => 'Beauty & Personal Care',
        'Makeup Artist'           => 'Beauty & Personal Care',
        'Barber'                  => 'Beauty & Personal Care',
        'Private Chef'            => 'Food & Catering',
        'Event Caterer'           => 'Food & Catering',
        'Phone Repair Technician' => 'Repair & Maintenance',
        'Computer Repair Technician' => 'Repair & Maintenance',
        'Courier'                 => 'Delivery & Moving',
        'Moving Service Provider' => 'Delivery & Moving',
        'Event Planner'           => 'Events',
        'DJ'                      => 'Events',
        'Graphic Designer'        => 'Graphic Design',
        'Web Developer'           => 'Web Development',
    ];

    private array $bios = [
        'Experienced professional dedicated to delivering high-quality work on time, every time.',
        'I take pride in clear communication and reliable service for every client.',
        'Years of hands-on experience helping clients get exactly what they need.',
        'Detail-oriented and committed to customer satisfaction on every project.',
    ];

    public function definition(): array
    {
        $techTitles = ['Graphic Designer', 'Web Developer'];
        $allTitles = array_keys($this->titleToCategory);
        $homeServiceTitles = array_diff($allTitles, $techTitles);

        $title = fake()->boolean(85)
            ? fake()->randomElement($homeServiceTitles)
            : fake()->randomElement($techTitles);

        return [
            'user_id' => User::factory()->provider(),
            'business_name' => fake()->company(),
            'bio' => fake()->randomElement($this->bios),
            'professional_title' => $title,
            'experience_years' => fake()->numberBetween(1, 15),
            'latitude' => fake()->latitude(8.9, 9.1),
            'longitude' => fake()->longitude(38.6, 38.9),
            'average_rating' => fake()->randomFloat(2, 3.5, 5.0),
            'completed_jobs' => fake()->numberBetween(0, 100),
            'verification_status' => 'verified',
        ];
    }
}