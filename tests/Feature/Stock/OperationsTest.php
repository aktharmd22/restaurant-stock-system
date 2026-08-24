<?php

use App\Enums\RoleName;
use App\Models\LocalPurchase;
use App\Models\StockCount;
use App\Models\StockLedger;
use App\Models\Wastage;
use App\Services\Requests\RequestWorkflowService;
use App\Services\Stock\StockViewService;

beforeEach(function () {
    seedRoles();

    $this->main = mainBranch();
    $this->park = subBranch('PARK');

    $this->onion = kgItem('Onion', ['storage_location' => 'Cold room']);

    $this->staff = userWithRole(RoleName::BranchStaff, $this->park);
    $this->manager = userWithRole(RoleName::BranchManager, $this->park);
    $this->admin = userWithRole(RoleName::MainAdmin, $this->main);

    giveStock($this->park, $this->onion, 20);
    giveStock($this->main, $this->onion, 100);
});

/*
|--------------------------------------------------------------------------
| Waste
|--------------------------------------------------------------------------
*/

it('takes waste off the shelf and says who threw it away', function () {
    $this->actingAs($this->staff)
        ->post('/waste', [
            'item_id' => $this->onion->id,
            'qty' => 2.5,
            'reason' => 'spoiled',
            'note' => 'Left out overnight',
        ])
        ->assertSessionHas('success', 'Onion taken off your stock.');

    $wastage = Wastage::withoutBranchScope()->first();

    expect($wastage->qty)->toBe(2500)
        ->and($wastage->recorded_by)->toBe($this->staff->id)
        ->and(onHand($this->park, $this->onion))->toBe(17500);
});

it('records waste in the ledger like every other movement', function () {
    $this->actingAs($this->staff)->post('/waste', [
        'item_id' => $this->onion->id,
        'qty' => 1,
        'reason' => 'expired',
    ]);

    $movement = StockLedger::withoutBranchScope()
        ->where('movement_type', 'wastage')
        ->first();

    expect($movement->qty_delta)->toBe(-1000)
        ->and($movement->reference_type)->toBe('Wastage')
        ->and($movement->branch_id)->toBe($this->park->id);
});

it('will not let a branch throw away more than it has', function () {
    $this->actingAs($this->staff)
        ->post('/waste', ['item_id' => $this->onion->id, 'qty' => 50, 'reason' => 'spoiled'])
        ->assertSessionHas('error');

    expect(onHand($this->park, $this->onion))->toBe(20000);
});

it('asks what happened before recording waste', function () {
    $this->actingAs($this->staff)
        ->post('/waste', ['item_id' => $this->onion->id, 'qty' => 1])
        ->assertSessionHasErrors(['reason' => 'Pick what happened to it.']);
});

it('shows a branch only its own waste', function () {
    $lake = subBranch('LAKE');
    $lakeStaff = userWithRole(RoleName::BranchStaff, $lake);
    giveStock($lake, $this->onion, 10);

    $this->actingAs($lakeStaff)->post('/waste', [
        'item_id' => $this->onion->id, 'qty' => 1, 'reason' => 'spoiled',
    ]);
    $this->actingAs($this->staff)->post('/waste', [
        'item_id' => $this->onion->id, 'qty' => 2, 'reason' => 'spoiled',
    ]);

    $this->actingAs($this->staff)
        ->get('/waste')
        ->assertInertia(fn ($page) => $page->has('entries.data', 1));
});

/*
|--------------------------------------------------------------------------
| Counting
|--------------------------------------------------------------------------
*/

it('snapshots what the books say when counting starts', function () {
    $this->actingAs($this->admin)->post('/admin/stock/count', ['branch' => $this->main->id]);

    $count = StockCount::withoutBranchScope()->first();
    $line = $count->lines()->where('item_id', $this->onion->id)->first();

    expect($count->status)->toBe('open')
        ->and($line->system_qty)->toBe(100000);
});

it('makes the shelf win, and writes down the difference', function () {
    $this->actingAs($this->admin)->post('/admin/stock/count', ['branch' => $this->main->id]);

    $count = StockCount::withoutBranchScope()->first();
    $line = $count->lines()->where('item_id', $this->onion->id)->first();

    // Counted 94 kg where the books said 100 kg.
    $this->actingAs($this->admin)
        ->post("/admin/stock/count/{$count->id}/apply", [
            'lines' => [$line->id => ['counted' => 94]],
            'note' => 'Weighed everything in the cold room',
        ])
        ->assertRedirect();

    expect(onHand($this->main, $this->onion))->toBe(94000)
        ->and($line->fresh()->difference)->toBe(-6000)
        ->and($count->fresh()->status)->toBe('applied');

    $correction = StockLedger::withoutBranchScope()->where('movement_type', 'adjustment')->first();

    expect($correction->qty_delta)->toBe(-6000)
        ->and($correction->reference_type)->toBe('StockCountLine');
});

it('refuses to apply a count without a reason', function () {
    $this->actingAs($this->admin)->post('/admin/stock/count', ['branch' => $this->main->id]);
    $count = StockCount::withoutBranchScope()->first();
    $line = $count->lines()->first();

    $this->actingAs($this->admin)
        ->post("/admin/stock/count/{$count->id}/apply", [
            'lines' => [$line->id => ['counted' => 1]],
            'note' => '',
        ])
        ->assertSessionHasErrors(['note' => 'Say why the numbers are different before applying the count.']);

    expect($count->fresh()->status)->toBe('open');
});

it('will not apply the same count twice', function () {
    $this->actingAs($this->admin)->post('/admin/stock/count', ['branch' => $this->main->id]);
    $count = StockCount::withoutBranchScope()->first();
    $line = $count->lines()->where('item_id', $this->onion->id)->first();

    $payload = ['lines' => [$line->id => ['counted' => 90]], 'note' => 'Counted'];

    $this->actingAs($this->admin)->post("/admin/stock/count/{$count->id}/apply", $payload);
    $this->actingAs($this->admin)->post("/admin/stock/count/{$count->id}/apply", $payload)
        ->assertSessionHas('error', 'This count has already been applied.');

    expect(onHand($this->main, $this->onion))->toBe(90000);
});

it('keeps branch staff out of counting', function () {
    $this->actingAs($this->staff)
        ->post('/admin/stock/count', ['branch' => $this->park->id])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Emergency buying
|--------------------------------------------------------------------------
*/

it('does not add stock from a local purchase until the admin approves it', function () {
    $this->actingAs($this->manager)
        ->post('/local-purchases', [
            'item_id' => $this->onion->id,
            'qty' => 5,
            'amount' => 250,
            'reason' => 'Ran out during lunch',
        ])
        ->assertSessionHas('success', 'Sent to the main store to approve.');

    $purchase = LocalPurchase::withoutBranchScope()->first();

    expect($purchase->status)->toBe('waiting')
        ->and($purchase->qty)->toBe(5000)
        // Nothing has moved yet.
        ->and(onHand($this->park, $this->onion))->toBe(20000);
});

it('adds the stock once the admin approves, valued from the bill', function () {
    $this->actingAs($this->manager)->post('/local-purchases', [
        'item_id' => $this->onion->id, 'qty' => 5, 'amount' => 250, 'reason' => 'Ran out',
    ]);

    $purchase = LocalPurchase::withoutBranchScope()->first();

    $this->actingAs($this->admin)
        ->post("/local-purchases/{$purchase->id}/approve")
        ->assertSessionHas('success');

    expect($purchase->fresh()->status)->toBe('approved')
        ->and(onHand($this->park, $this->onion))->toBe(25000);

    // 250 rupees for 5000 grams is 0.05 per gram.
    $movement = StockLedger::withoutBranchScope()->where('movement_type', 'purchase')
        ->where('reference_type', 'LocalPurchase')->first();

    expect((float) $movement->unit_cost)->toBe(0.05);
});

it('makes the admin say why when refusing a bill', function () {
    $this->actingAs($this->manager)->post('/local-purchases', [
        'item_id' => $this->onion->id, 'qty' => 5, 'amount' => 250, 'reason' => 'Ran out',
    ]);

    $purchase = LocalPurchase::withoutBranchScope()->first();

    $this->actingAs($this->admin)
        ->post("/local-purchases/{$purchase->id}/reject", ['note' => ''])
        ->assertSessionHasErrors('note');

    $this->actingAs($this->admin)
        ->post("/local-purchases/{$purchase->id}/reject", ['note' => 'Ask us first next time'])
        ->assertSessionHas('success');

    expect($purchase->fresh()->status)->toBe('rejected')
        ->and(onHand($this->park, $this->onion))->toBe(20000);
});

it('never lets a branch approve its own emergency buy', function () {
    $this->actingAs($this->manager)->post('/local-purchases', [
        'item_id' => $this->onion->id, 'qty' => 5, 'amount' => 250, 'reason' => 'Ran out',
    ]);

    $purchase = LocalPurchase::withoutBranchScope()->first();

    $this->actingAs($this->manager)
        ->post("/local-purchases/{$purchase->id}/approve")
        ->assertForbidden();

    expect(onHand($this->park, $this->onion))->toBe(20000);
});

/*
|--------------------------------------------------------------------------
| Reading stock
|--------------------------------------------------------------------------
*/

it('shows promised stock separately from free stock', function () {
    $workflow = app(RequestWorkflowService::class);

    $request = $workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 30]]);
    $workflow->approveAll($request, $this->admin);

    $row = app(StockViewService::class)->forBranch($this->main->id)->firstWhere('id', $this->onion->id);

    expect($row['on_hand_text'])->toBe('100 kg')
        ->and($row['reserved_text'])->toBe('30 kg')
        ->and($row['available_text'])->toBe('70 kg');
});

it('works out a use-by date for things that go off', function () {
    $milk = kgItem('Milk', [
        'base_unit' => 'ml', 'order_unit' => 'litre',
        'is_perishable' => true, 'shelf_life_days' => 3,
    ]);
    giveStock($this->park, $milk, 10);

    $row = app(StockViewService::class)->forBranch($this->park->id)->firstWhere('id', $milk->id);

    expect($row['use_by'])->toBe(now()->addDays(3)->toDateString());

    // Something that keeps has no use-by at all.
    $row = app(StockViewService::class)->forBranch($this->park->id)->firstWhere('id', $this->onion->id);
    expect($row['use_by'])->toBeNull();
});

it('shows a branch only its own stock screen', function () {
    $this->actingAs($this->staff)
        ->get('/b/stock')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Branch/Stock')->has('rows'));

    $this->actingAs($this->staff)->get('/admin/stock')->assertForbidden();
});
