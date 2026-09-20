<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\ProviderProfile;
use App\Models\CustomerProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Role::count() === 0) {
            $this->call([
                RoleSeeder::class,
                CategorySeeder::class,
                SkillSeeder::class,
            ]);
        }

        // Import primary account (elaf.ebrahim@aastustudent.edu.et)
        if (! User::where('email', 'elaf.ebrahim@aastustudent.edu.et')->exists()) {
            $providerRole = Role::where('name', 'provider')->first();
            if ($providerRole) {
                $user = User::create([
                    'role_id'           => $providerRole->id,
                    'first_name'        => 'Matt',
                    'last_name'         => 'Matt',
                    'email'             => 'elaf.ebrahim@aastustudent.edu.et',
                    'phone'             => '+25198768765',
                    'password'          => '$2y$12$BHUqNYuwJL6dSvshHB5bVe/1foFIQ5AbL/r.D9s.e.qnR7uqHr5Ri',
                    'account_status'    => 'active',
                    'email_verified_at' => now(),
                ]);

                ProviderProfile::create([
                    'user_id'             => $user->id,
                    'bio'                 => 'I work as a software engineer specifically as web developer and i work on full stack development. I have worked with different well known local companies and potentially have a great experience. I work both remotely and in person based on the type of the project.',
                    'professional_title'  => 'Web Developer',
                    'experience_years'    => 0,
                    'verification_status' => 'verified',
                ]);
            }
        }

        // Create default test customer and admins if not present
        if (! User::where('email', 'customer@skilllink.test')->exists()) {
            $customerRole = Role::where('name', 'customer')->first();
            if ($customerRole) {
                $c = User::create([
                    'role_id'           => $customerRole->id,
                    'first_name'        => 'Elaf',
                    'last_name'         => 'Customer',
                    'email'             => 'customer@skilllink.test',
                    'phone'             => '0911000001',
                    'password'          => Hash::make('password'),
                    'account_status'    => 'active',
                    'email_verified_at' => now(),
                ]);
                CustomerProfile::create(['user_id' => $c->id]);
            }
        }

        if (app()->environment('local')) {
            $this->call([
                UserSeeder::class,
            ]);
        }
    }
}