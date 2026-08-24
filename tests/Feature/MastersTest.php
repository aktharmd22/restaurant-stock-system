<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\BranchItemSetting;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;

beforeEach(function () {
    seedRoles();
    $this->main = mainBranch();
    $this->owner = userWithRole(RoleName::SuperAdmin, $this->main);
});

it('adds a branch with its own cut-off time', function () {
    $this->actingAs($this->owner)
        ->post('/admin/settings/branches', [
            'name' => 'Harbour View',
            'code' => 'harb',
            'type' => 'sub',
            'phone' => '9000001111',
            'address' => 'Dock Road',
            'cutoff_time' => '17:30',
            'is_active' => true,
        ])
        ->assertRedirect('/admin/settings/branches');

    $branch = Branch::where('code', 'HARB')->first();

    expect($branch)->not->toBeNull()
        ->and($branch->cutoff_time)->toBe('17:30:00')
        ->and($branch->name)->toBe('Harbour View');
});

it('will not let two branches share a code', function () {
    Branch::factory()->create(['code' => 'PARK']);

    $this->actingAs($this->owner)
        ->post('/admin/settings/branches', [
            'name' => 'Another Park', 'code' => 'PARK', 'type' => 'sub', 'cutoff_time' => '18:00',
        ])
        ->assertSessionHasErrors(['code' => 'Another branch already uses that code.']);
});

it('never switches off the main store', function () {
    $this->actingAs($this->owner)
        ->post("/admin/settings/branches/{$this->main->id}/toggle")
        ->assertSessionHas('error', 'The main store cannot be switched off.');

    expect($this->main->fresh()->is_active)->toBeTrue();
});

it('adds a person with a role and a branch', function () {
    $park = subBranch();

    $this->actingAs($this->owner)
        ->post('/admin/settings/users', [
            'name' => 'Sita Raman',
            'phone' => '98765 43210',
            'email' => 'sita@demo.test',
            'branch_id' => $park->id,
            'role' => RoleName::BranchManager->value,
            'password' => 'first-password',
            'is_active' => true,
        ])
        ->assertRedirect('/admin/settings/users');

    $user = User::where('email', 'sita@demo.test')->first();

    expect($user->phone)->toBe('9876543210')          // spaces stripped
        ->and($user->branch_id)->toBe($park->id)
        ->and($user->hasRole(RoleName::BranchManager->value))->toBeTrue();
});

it('gives a person a new password the admin can read out', function () {
    $staff = userWithRole(RoleName::BranchStaff, subBranch());
    $before = $staff->password;

    $this->actingAs($this->owner)
        ->post("/admin/settings/users/{$staff->id}/new-password")
        ->assertSessionHas('success');

    expect($staff->fresh()->password)->not->toBe($before);
});

it('will not let an admin switch off their own account', function () {
    $this->actingAs($this->owner)
        ->post("/admin/settings/users/{$this->owner->id}/toggle")
        ->assertSessionHas('error', 'You cannot switch off your own account.');

    expect($this->owner->fresh()->is_active)->toBeTrue();
});

it('adds an item and stores its par levels in base units', function () {
    $category = Category::factory()->create(['name' => 'Vegetables']);
    $park = subBranch();

    $this->actingAs($this->owner)
        ->post('/admin/settings/items', [
            'name' => 'Onion',
            'category_id' => $category->id,
            'base_unit' => 'g',
            'order_unit' => 'kg',
            'conversion_factor' => 1000,
            'step' => 0.5,
            'is_perishable' => true,
            'shelf_life_days' => 14,
            'storage_location' => 'Cold room',
            'is_active' => true,
            'par_levels' => [
                // Typed in kilograms by a person...
                $this->main->id => ['par' => 40, 'reorder' => 12],
                $park->id => ['par' => 12.5, 'reorder' => 4],
            ],
        ])
        ->assertRedirect('/admin/settings/items');

    $item = Item::where('name', 'Onion')->first();

    expect($item->step_x100)->toBe(50)
        ->and($item->shelf_life_days)->toBe(14);

    // ...and stored in grams.
    $mainSetting = BranchItemSetting::where('item_id', $item->id)->where('branch_id', $this->main->id)->first();
    $parkSetting = BranchItemSetting::where('item_id', $item->id)->where('branch_id', $park->id)->first();

    expect($mainSetting->par_level)->toBe(40000)
        ->and($mainSetting->reorder_level)->toBe(12000)
        ->and($parkSetting->par_level)->toBe(12500);
});

it('asks how long a perishable item keeps', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->owner)
        ->post('/admin/settings/items', [
            'name' => 'Fresh cream',
            'category_id' => $category->id,
            'base_unit' => 'ml', 'order_unit' => 'litre', 'conversion_factor' => 1000,
            'step' => 0.5,
            'is_perishable' => true,
            'shelf_life_days' => null,
        ])
        ->assertSessionHasErrors(['shelf_life_days' => 'Say how many days it keeps.']);
});

it('adds an item group', function () {
    $this->actingAs($this->owner)
        ->post('/admin/settings/categories', ['name' => 'Bakery', 'sort_order' => 70])
        ->assertSessionHas('success', 'Bakery added.');

    expect(Category::where('name', 'Bakery')->exists())->toBeTrue();
});

it('keeps branch people out of every master screen', function () {
    $manager = userWithRole(RoleName::BranchManager, subBranch());

    $this->actingAs($manager)->get('/admin/settings/branches')->assertForbidden();
    $this->actingAs($manager)->get('/admin/settings/users')->assertForbidden();
    $this->actingAs($manager)->get('/admin/settings/items')->assertForbidden();
    $this->actingAs($manager)->post('/admin/settings/categories', ['name' => 'Sneaky'])->assertForbidden();

    expect(Category::where('name', 'Sneaky')->exists())->toBeFalse();
});

it('stops the main admin creating people, which is the owner\'s job', function () {
    $admin = userWithRole(RoleName::MainAdmin, $this->main);

    $this->actingAs($admin)->get('/admin/settings/users')->assertForbidden();

    // But they can manage the catalogue.
    $this->actingAs($admin)->get('/admin/settings/items')->assertOk();
});
