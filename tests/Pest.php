<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\Stock\StockLedgerService;
use App\Support\Quantity;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * The concurrency suite needs data that is actually committed, so a second
 * database connection can see it. RefreshDatabase keeps everything inside an
 * open transaction, which would make that impossible.
 */
pest()->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Concurrency');

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

function userWithRole(RoleName $role, ?Branch $branch = null, array $attributes = []): User
{
    $user = User::factory()->create([
        'branch_id' => $branch?->id,
        ...$attributes,
    ]);

    $user->assignRole($role->value);

    return $user->fresh();
}

/** An item measured in kilograms, stored in grams. */
function kgItem(string $name = 'Onion', array $attributes = []): Item
{
    return Item::factory()->create([
        'category_id' => Category::factory(),
        'name' => $name,
        'base_unit' => 'g',
        'order_unit' => 'kg',
        'conversion_factor' => 1000,
        'step_x100' => 50,
        ...$attributes,
    ]);
}

/** Put stock on the shelf the only way the app allows: through the ledger. */
function giveStock(Branch $branch, Item $item, float $orderUnits, ?float $unitCost = null): void
{
    // No reference: movements without one are exempt from the idempotency
    // index, so a test can top up the same item more than once.
    app(StockLedgerService::class)->purchase(
        $branch->id,
        Quantity::fromOrderUnit($orderUnits, $item),
        null,
        $unitCost,
    );
}

function onHand(Branch $branch, Item $item): int
{
    return (int) (\App\Models\StockBalance::withoutBranchScope()
        ->where('branch_id', $branch->id)
        ->where('item_id', $item->id)
        ->value('qty_on_hand') ?? 0);
}

function reserved(Branch $branch, Item $item): int
{
    return (int) (\App\Models\StockBalance::withoutBranchScope()
        ->where('branch_id', $branch->id)
        ->where('item_id', $item->id)
        ->value('qty_reserved') ?? 0);
}
