<?php

namespace Database\Factories;

use App\Models\ProviderProfile;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
   private array $offeringsByCategory = [
    'Home Services' => [
        ['Plumbing Repair & Installation', 'Leak fixes, pipe replacement, and new fixture installation for kitchens and bathrooms.', 250, 800, 'hourly'],
        ['Electrical Wiring & Outlet Repair', 'Safe, code-compliant electrical repairs, outlet installation, and lighting fixes.', 300, 900, 'hourly'],
        ['House Painting', 'Interior and exterior painting, including surface prep and cleanup.', 3000, 15000, 'fixed'],
        ['Furniture & Carpentry Work', 'Custom shelving, door repair, and furniture assembly for your home or office.', 500, 3000, 'fixed'],
    ],
    'Automotive' => [
        ['General Vehicle Diagnostics & Repair', 'Full diagnostic check and repair for engine, brakes, and suspension issues.', 300, 1200, 'fixed'],
        ['Oil Change & Routine Maintenance', 'Oil change, filter replacement, and general maintenance check-up.', 200, 600, 'fixed'],
        ['Mobile Car Wash', 'Full exterior and interior car wash at your home or office.', 150, 400, 'fixed'],
        ['Tire & Brake Service', 'Tire rotation, replacement, and brake pad servicing.', 250, 900, 'fixed'],
    ],
    'Cleaning' => [
        ['Deep House Cleaning', 'A thorough top-to-bottom clean including kitchen, bathrooms, and all living areas.', 400, 1200, 'fixed'],
        ['Office Cleaning Service', 'Regular or one-time office cleaning, including desks, floors, and common areas.', 300, 1500, 'fixed'],
        ['Laundry & Ironing Service', 'Pickup, wash, dry, and iron service for households.', 150, 500, 'fixed'],
    ],
    'Education & Training' => [
        ['Mathematics Tutoring (Grade 9-12)', 'One-on-one math tutoring covering algebra, geometry, and exam preparation.', 150, 400, 'hourly'],
        ['English Language & Exam Prep', 'English grammar, writing, and conversation practice, including test preparation.', 150, 350, 'hourly'],
        ['Music Lessons (Piano/Guitar)', 'Beginner to intermediate music lessons at your home.', 200, 500, 'hourly'],
        ['Career Coaching', 'CV review, interview prep, and career planning sessions.', 300, 700, 'hourly'],
    ],
    'Beauty & Personal Care' => [
        ['Hairdressing & Styling', 'Cuts, coloring, and styling for special occasions or routine care.', 200, 900, 'fixed'],
        ['Makeup Artist (Events)', 'Professional makeup for weddings, graduations, and events.', 800, 2500, 'fixed'],
        ['Mobile Barber Service', 'Haircut and grooming service at your home or office.', 150, 350, 'fixed'],
        ['Nail Technician', 'Manicure and pedicure services at your location.', 200, 600, 'fixed'],
    ],
    'Food & Catering' => [
        ['Private Chef / Home Cooking', 'Home-cooked traditional and international meals prepared in your kitchen.', 300, 800, 'hourly'],
        ['Event Catering (Small Gatherings)', 'Full catering service for birthdays and small parties, up to 30 guests.', 3000, 12000, 'fixed'],
        ['Custom Cake & Baking', 'Custom cakes and baked goods for celebrations.', 500, 3000, 'fixed'],
    ],
    'Repair & Maintenance' => [
        ['Phone Screen & Battery Repair', 'Fast, reliable smartphone screen and battery replacement.', 300, 1500, 'fixed'],
        ['Computer & Laptop Repair', 'Hardware diagnostics, virus removal, and performance tune-ups.', 400, 1800, 'fixed'],
        ['Appliance Repair', 'Repair for refrigerators, washing machines, and other home appliances.', 300, 1200, 'fixed'],
    ],
    'Delivery & Moving' => [
        ['Local Delivery & Courier', 'Same-day package and parcel delivery across the city.', 100, 400, 'fixed'],
        ['Home & Office Moving', 'Full moving service including loading, transport, and unloading.', 1500, 8000, 'fixed'],
    ],
    'Events' => [
        ['Event Planning & Coordination', 'Full-service planning for weddings, birthdays, and corporate events.', 5000, 30000, 'fixed'],
        ['Event Decoration', 'Venue decoration and setup for celebrations.', 2000, 10000, 'fixed'],
        ['DJ & MC Services', 'Music and hosting services for events and parties.', 1500, 6000, 'fixed'],
    ],
    'Graphic Design' => [
        ['Logo Design & Brand Identity', 'Clean, modern logo design and complete brand identity packages.', 800, 3500, 'fixed'],
        ['Social Media Graphics Package', 'A set of 10 custom-designed social media posts and story templates.', 500, 1800, 'fixed'],
    ],
    'Web Development' => [
        ['Custom Website Build', 'Responsive websites built from scratch, including a contact form and basic SEO.', 8000, 35000, 'fixed'],
        ['WordPress Site Setup', 'Full WordPress installation, theme customization, and plugin setup.', 4000, 15000, 'fixed'],
    ],
];
 public function definition(): array
{
    $category = Category::inRandomOrder()->first() ?? Category::factory()->create();
    $options = $this->offeringsByCategory[$category->name] ?? [
        ['Professional Service', 'Reliable, high-quality service tailored to your needs.', 300, 2000, 'fixed'],
    ];
    [$title, $description, $min, $max, $priceType] = fake()->randomElement($options);

    return [
        'provider_id' => ProviderProfile::factory(),
        'category_id' => $category->id,
        'title' => $title,
        'description' => $description,
        'price' => fake()->numberBetween($min, $max),
        'price_type' => $priceType,
        'duration' => fake()->numberBetween(30, 240),
        'service_status' => 'active',
    ];
}
public function forCategory(string $categoryName): static
{
    return $this->state(function () use ($categoryName) {
        $category = Category::where('name', $categoryName)->first()
            ?? Category::inRandomOrder()->first()
            ?? Category::factory()->create();

        $options = $this->offeringsByCategory[$category->name] ?? [
            ['Professional Service', 'Reliable, high-quality service tailored to your needs.', 300, 2000, 'fixed'],
        ];
        [$title, $description, $min, $max, $priceType] = fake()->randomElement($options);

        return [
            'category_id' => $category->id,
            'title'       => $title,
            'description' => $description,
            'price'       => fake()->numberBetween($min, $max),
            'price_type'  => $priceType,
        ];
    });
}
}