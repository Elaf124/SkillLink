<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            // Graphic Design
            'Photoshop',
            'Illustrator',
            'Logo Design',
            'Brand Identity',
            'UI/UX Design',

            // Web Development
            'Laravel',
            'Vue.js',
            'React',
            'Node.js',
            'WordPress',
            'API Integration',

            // Home Repair
            'Plumbing',
            'Electrical Wiring',
            'Carpentry',
            'Painting',
            'Appliance Repair',

            // Tutoring
            'Mathematics Tutoring',
            'English Tutoring',
            'Physics Tutoring',
            'Exam Preparation',

            // Cleaning
            'Deep Cleaning',
            'Office Cleaning',
            'Carpet Cleaning',
            'Move-in/Move-out Cleaning',
        ];

        $now = now();

        DB::table('skills')->insert(
            collect($skills)->map(fn ($skill) => [
                'skill_name' => $skill,
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray()
        );
    }
}