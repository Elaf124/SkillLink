<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    private array $firstNames = [
        'Abebe', 'Almaz', 'Bekele', 'Birtukan', 'Dawit', 'Eleni', 'Fikru',
        'Genet', 'Girma', 'Hana', 'Kebede', 'Liya', 'Meron', 'Mulugeta',
        'Rahel', 'Samuel', 'Selamawit', 'Tewodros', 'Tigist', 'Yohannes',
        'Yordanos', 'Zewditu', 'Abel', 'Betelhem', 'Daniel', 'Eyerusalem',
        'Feven', 'Henok', 'Kalkidan', 'Natnael',
    ];

    private array $lastNames = [
        'Alemu', 'Bekele', 'Desta', 'Gebre', 'Girma', 'Hailu', 'Kebede',
        'Mekonnen', 'Mulu', 'Negash', 'Tadesse', 'Tesfaye', 'Wolde',
        'Yohannes', 'Zeleke', 'Assefa', 'Getachew', 'Haile', 'Lemma', 'Worku',
    ];

    public function definition(): array
    {
        return [
            'role_id' => Role::where('name', 'customer')->first()?->id ?? Role::factory(),
            'first_name' => fake()->randomElement($this->firstNames),
            'last_name' => fake()->randomElement($this->lastNames),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('09########'),
            'password' => bcrypt('password'),
            'profile_photo' => null,
            'timezone' => 'Africa/Addis_Ababa',
            'country' => 'Ethiopia',
            'display_currency' => 'ETB',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ];
    }

    public function provider(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::where('name', 'provider')->first()->id,
        ]);
    }

    public function adminSupport(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::where('name', 'admin_support')->first()->id,
        ]);
    }

    public function adminFinance(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::where('name', 'admin_finance')->first()->id,
        ]);
    }
}