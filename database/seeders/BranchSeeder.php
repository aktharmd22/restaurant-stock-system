<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['name' => 'Main Store', 'code' => 'MAIN', 'type' => 'main', 'cutoff_time' => '18:00:00', 'phone' => '9000000000', 'address' => 'Central kitchen and store'],
            ['name' => 'Park Street', 'code' => 'PARK', 'type' => 'sub', 'cutoff_time' => '18:00:00', 'phone' => '9000000011', 'address' => '12 Park Street'],
            ['name' => 'Lake Road', 'code' => 'LAKE', 'type' => 'sub', 'cutoff_time' => '17:30:00', 'phone' => '9000000012', 'address' => '48 Lake Road'],
            ['name' => 'Airport Plaza', 'code' => 'AIRP', 'type' => 'sub', 'cutoff_time' => '19:00:00', 'phone' => '9000000013', 'address' => 'Airport Plaza, Gate 2'],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(['code' => $branch['code']], $branch);
        }
    }
}
