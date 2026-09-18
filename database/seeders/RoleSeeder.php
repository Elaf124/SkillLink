<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insert([
            [
                'name' => 'customer',
                'description' => 'Books services and posts jobs',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'provider',
                'description' => 'Offers services and fulfills bookings',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'admin_support',
                'description' => 'Handles disputes, user reports, and provider verification',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'admin_finance',
                'description' => 'Manages payouts, wallet transactions, and payment monitoring',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}