<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Role::count() === 0) {
            $this->call([
                RoleSeeder::class,
                CategorySeeder::class,
                SkillSeeder::class,
                UserSeeder::class,
            ]);
        }
    }
}