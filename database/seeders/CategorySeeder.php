<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $structure = [
            'Home Services' => ['Electrician', 'Plumber', 'Carpenter', 'Painter'],
            'Automotive' => ['Mechanic', 'Car Electrician', 'Car Wash', 'Tire Service'],
            'Cleaning' => ['House Cleaner', 'Office Cleaner', 'Laundry Service'],
            'Education & Training' => ['Tutor', 'Language Teacher', 'Music Teacher', 'Career Coach'],
            'Beauty & Personal Care' => ['Hairdresser', 'Makeup Artist', 'Barber', 'Nail Technician'],
            'Food & Catering' => ['Cook', 'Caterer', 'Baker', 'Personal Chef'],
            'Repair & Maintenance' => ['Phone Repair', 'Computer Repair', 'Appliance Repair'],
            'Delivery & Moving' => ['Delivery Worker', 'Moving Service', 'Courier'],
            'Events' => ['Event Planner', 'Decorator', 'DJ', 'MC'],
            'Construction' => ['Mason', 'Welder', 'Roofer', 'Construction Worker'],
            'Fashion & Tailoring' => ['Tailor', 'Dressmaker', 'Fashion Designer', 'Alterations'],
            'Health & Wellness' => ['Fitness Trainer', 'Nutrition Coach', 'Massage Therapist'],
            'Agriculture & Gardening' => ['Gardener', 'Landscaping', 'Farm Services'],
            'Security' => ['Security Guard', 'CCTV Installer', 'Locksmith'],
            'Pet Services' => ['Pet Groomer', 'Pet Sitter', 'Dog Walker'],
            'Media & Photography' => ['Photographer', 'Videographer', 'Drone Operator'],
            'Business & Professional' => ['Accountant', 'Consultant', 'Virtual Assistant'],
            'Marketing & Sales' => ['Social Media Manager', 'SEO Specialist', 'Sales Representative'],
            'Design & Creative' => ['Graphic Designer', 'UI/UX Designer', 'Illustrator', 'Video Editor'],
            'Technology & IT' => ['Web Developer', 'Mobile Developer', 'IT Support', 'Database Specialist'],
        ];

        foreach ($structure as $parentName => $children) {
            $parentId = DB::table('categories')->insertGetId([
                'name' => $parentName,
                'description' => null,
                'parent_category_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($children as $childName) {
                DB::table('categories')->insert([
                    'name' => $childName,
                    'description' => null,
                    'parent_category_id' => $parentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}