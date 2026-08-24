<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'Green Valley Vegetables', 'contact_person' => 'Ramesh Patil', 'phone' => '9810000001', 'address' => 'Wholesale market, Gate 4'],
            ['name' => 'Coastal Meat and Fish', 'contact_person' => 'Joseph D Souza', 'phone' => '9810000002', 'address' => 'Harbour Road'],
            ['name' => 'Sri Balaji Provisions', 'contact_person' => 'Lakshmi Iyer', 'phone' => '9810000003', 'address' => 'Main Bazaar'],
            ['name' => 'Everest Spice House', 'contact_person' => 'Anil Gupta', 'phone' => '9810000004', 'address' => 'Spice Market Lane 3'],
            ['name' => 'Amrit Dairy', 'contact_person' => 'Sukhbir Singh', 'phone' => '9810000005', 'address' => 'Dairy Colony'],
            ['name' => 'PackWell Supplies', 'contact_person' => 'Nita Shah', 'phone' => '9810000006', 'address' => 'Industrial Estate'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(['name' => $supplier['name']], $supplier + ['is_active' => true]);
        }
    }
}
