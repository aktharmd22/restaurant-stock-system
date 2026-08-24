<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function seedRoles(): void
{
    test()->seed(RoleSeeder::class);
}

function mainBranch(): Branch
{
    return Branch::firstOrCreate(
        ['code' => 'MAIN'],
        ['name' => 'Main Store', 'type' => 'main', 'cutoff_time' => '18:00:00', 'timezone' => 'Asia/Kolkata', 'is_active' => true],
    );
}

function subBranch(string $code = 'PARK'): Branch
{
    return Branch::firstOrCreate(
        ['code' => $code],
        ['name' => "{$code} Branch", 'type' => 'sub', 'cutoff_time' => '18:00:00', 'timezone' => 'Asia/Kolkata', 'is_active' => true],
    );
}

/**
 * Create a user with a role. Roles must already be seeded.
 */
function userWithRole(RoleName $role, ?Branch $branch = null, array $attributes = []): User
{
    $user = User::factory()->create([
        'branch_id' => $branch?->id,
        ...$attributes,
    ]);

    $user->assignRole($role->value);

    return $user->fresh();
}
