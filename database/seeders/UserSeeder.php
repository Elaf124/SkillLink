<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\CustomerProfile;
use App\Models\ProviderProfile;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $customerRole = Role::where('name', 'customer')->first();
        $providerRole = Role::where('name', 'provider')->first();
        $supportRole  = Role::where('name', 'admin_support')->first();
        $financeRole  = Role::where('name', 'admin_finance')->first();

      

        $customer = User::create([
            'role_id' => $customerRole->id,
            'first_name' => 'Elaf',
            'last_name' => 'Test',
            'email' => 'customer@skilllink.test',
            'phone' => '0911000001',
            'password' => Hash::make('password'),
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        CustomerProfile::create([
            'user_id' => $customer->id,
            'preferred_language' => 'English',
        ]);

        $provider = User::create([
            'role_id' => $providerRole->id,
            'first_name' => 'Yohannes',
            'last_name' => 'Provider',
            'email' => 'provider@skilllink.test',
            'phone' => '0911000002',
            'password' => Hash::make('password'),
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $providerProfile = ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'Yohannes Design Studio',
            'bio' => 'Graphic designer with 5 years experience specializing in brand identity, UI/UX systems, and vector illustration.',
            'professional_title' => 'Senior Graphic & Brand Designer',
            'experience_years' => 5,
            'experience' => [
                [
                    'role' => 'Lead Brand Designer',
                    'company' => 'Afro Creative Agency',
                    'period' => '2022 - Present',
                    'description' => 'Designed visual identities and design systems for over 35 tech and retail brands across East Africa.'
                ],
                [
                    'role' => 'UI/UX Visual Designer',
                    'company' => 'Ethio Digital Labs',
                    'period' => '2019 - 2022',
                    'description' => 'Created web and mobile UI prototypes, wireframes, and design guidelines for enterprise clients.'
                ]
            ],
            'education' => [
                [
                    'qualification' => 'B.A. in Fine Arts & Graphic Communication',
                    'institution' => 'Alle School of Fine Arts and Design, AAU',
                    'year' => '2019'
                ]
            ],
            'verification_status' => 'verified',
        ]);
        $providerProfile->verifications()->create([
            'document_category' => 'certification',
            'document_type' => 'trade_certificate',
            'document_name' => 'Certified Adobe Professional (Visual Design)',
            'issuing_organization' => 'Adobe Certified Associate',
            'issue_date' => '2021-06-15',
            'verification_status' => 'approved',
        ]);
        Service::factory()->count(2)->create([
            'provider_id' => $providerProfile->id,
        ]);

        User::create([
            'role_id' => $supportRole->id,
            'first_name' => 'Sara',
            'last_name' => 'Support',
            'email' => 'support@skilllink.test',
            'phone' => '0911000003',
            'password' => Hash::make('password'),
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        User::create([
            'role_id' => $financeRole->id,
            'first_name' => 'Dawit',
            'last_name' => 'Finance',
            'email' => 'finance@skilllink.test',
            'phone' => '0911000004',
            'password' => Hash::make('password'),
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

    
        $categoriesByName = \App\Models\Category::pluck('id', 'name');
$titleToCategory = (new \Database\Factories\ProviderProfileFactory())->titleToCategory;

ProviderProfile::factory()
    ->count(20)
    ->create()
    ->each(function ($provider) use ($titleToCategory) {
        $matchedCategoryName = $titleToCategory[$provider->professional_title] ?? array_rand(array_flip($titleToCategory));

        \App\Models\Service::factory()
            ->count(3)
            ->forCategory($matchedCategoryName)
            ->create(['provider_id' => $provider->id]);
    });
    }
}