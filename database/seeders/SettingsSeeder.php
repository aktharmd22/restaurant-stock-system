<?php

namespace Database\Seeders;

use App\Support\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        app(Settings::class)->setMany([
            'business_name' => 'Spice Route Kitchens',
            'business_tagline' => 'Stock, sorted. Every branch, every day.',
            'business_phone' => '9000000000',
            'business_address' => 'Central kitchen and store',
            'currency_symbol' => '₹',
        ]);
    }
}
