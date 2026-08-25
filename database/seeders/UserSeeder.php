<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $main = Branch::where('code', 'MAIN')->firstOrFail();
        $park = Branch::where('code', 'PARK')->firstOrFail();
        $lake = Branch::where('code', 'LAKE')->firstOrFail();
        $airport = Branch::where('code', 'AIRP')->firstOrFail();

        // Every demo account uses the same password so the app can be shown
        // without typing anything in. One person per job, no more.
        $people = [
            ['Anita Rao', 'owner@demo.test', '9000000001', $main->id, RoleName::SuperAdmin],
            ['Vikram Shah', 'store@demo.test', '9000000002', $main->id, RoleName::MainAdmin],

            ['Priya Nair', 'park.manager@demo.test', '9000000003', $park->id, RoleName::BranchManager],
            ['Imran Sheikh', 'lake.manager@demo.test', '9000000005', $lake->id, RoleName::BranchManager],
            ['Sunil Kumar', 'airport.manager@demo.test', '9000000007', $airport->id, RoleName::BranchManager],
        ];

        foreach ($people as [$name, $email, $phone, $branchId, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => $phone,
                    'branch_id' => $branchId,
                    'password' => 'password',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role->value]);
        }
    }
}
