<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;

beforeEach(function () {
    seedRoles();
    $this->main = mainBranch();
    $this->owner = userWithRole(RoleName::SuperAdmin, $this->main);
});

/*
 * The rule the whole app is built on is that the ledger is the truth and
 * nothing that has moved stock is ever thrown away. Delete exists for the
 * other case: something typed by mistake, five minutes ago, never used.
 */

it('deletes an item that has never held stock', function () {
    $item = kgItem('Typed By Mistake');

    $this->actingAs($this->owner)
        ->delete("/admin/settings/items/{$item->id}")
        ->assertSessionHas('success', 'Typed By Mistake deleted.');

    expect(Item::find($item->id))->toBeNull()
        ->and(Item::withTrashed()->find($item->id))->not->toBeNull();
});

it('refuses to delete an item with stock history, and says to hide it', function () {
    $item = kgItem('Onion');
    giveStock($this->main, $item, 10);

    $this->actingAs($this->owner)
        ->delete("/admin/settings/items/{$item->id}")
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'Hide it instead'));

    expect(Item::find($item->id))->not->toBeNull();
});

it('deletes an empty group but not one still holding items', function () {
    $empty = Category::factory()->create(['name' => 'Nothing In Here']);
    $used = Category::factory()->create(['name' => 'Vegetables']);
    kgItem('Onion', ['category_id' => $used->id]);

    $this->actingAs($this->owner)
        ->delete("/admin/settings/categories/{$empty->id}")
        ->assertSessionHas('success', 'Nothing In Here deleted.');

    $this->actingAs($this->owner)
        ->delete("/admin/settings/categories/{$used->id}")
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'still holds 1 item'));

    expect(Category::find($empty->id))->toBeNull()
        ->and(Category::find($used->id))->not->toBeNull();
});

it('never deletes the main store', function () {
    $this->actingAs($this->owner)
        ->delete("/admin/settings/branches/{$this->main->id}")
        ->assertSessionHas('error', 'The main store cannot be deleted.');

    expect(Branch::find($this->main->id))->not->toBeNull();
});

it('refuses to delete a branch that still has people at it', function () {
    $branch = subBranch('HARB');
    userWithRole(RoleName::BranchStaff, $branch);

    $this->actingAs($this->owner)
        ->delete("/admin/settings/branches/{$branch->id}")
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'person is'));

    expect(Branch::find($branch->id))->not->toBeNull();
});

it('deletes a branch that was added by mistake and never used', function () {
    $branch = subBranch('MIST');

    $this->actingAs($this->owner)
        ->delete("/admin/settings/branches/{$branch->id}")
        ->assertSessionHas('success');

    expect(Branch::find($branch->id))->toBeNull();
});

it('refuses to delete a branch that has held stock', function () {
    $branch = subBranch('HELD');
    $item = kgItem('Onion');
    giveStock($branch, $item, 5);

    $this->actingAs($this->owner)
        ->delete("/admin/settings/branches/{$branch->id}")
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'stock history'));

    expect(Branch::find($branch->id))->not->toBeNull();
});

it('will not let anyone delete their own account', function () {
    $this->actingAs($this->owner)
        ->delete("/admin/settings/users/{$this->owner->id}")
        ->assertSessionHas('error', 'You cannot delete your own account.');

    expect(User::find($this->owner->id))->not->toBeNull();
});

it('deletes a person who has never moved stock', function () {
    $person = userWithRole(RoleName::BranchStaff, subBranch());

    $this->actingAs($this->owner)
        ->delete("/admin/settings/users/{$person->id}")
        ->assertSessionHas('success');

    expect(User::find($person->id))->toBeNull();
});

it('refuses to delete a supplier that is on an order', function () {
    $supplier = Supplier::create(['name' => 'Everest Spice House', 'is_active' => true]);

    PurchaseOrder::create([
        'branch_id' => $this->main->id,
        'supplier_id' => $supplier->id,
        'po_number' => 'PO-0001',
        'status' => 'ordered',
        'created_by' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->delete("/admin/suppliers/{$supplier->id}")
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'cannot be deleted'));

    expect(Supplier::find($supplier->id))->not->toBeNull();
});

it('keeps a branch user away from every delete', function () {
    $staff = userWithRole(RoleName::BranchStaff, subBranch());
    $item = kgItem('Onion');

    $this->actingAs($staff)->delete("/admin/settings/items/{$item->id}")->assertForbidden();

    expect(Item::find($item->id))->not->toBeNull();
});
