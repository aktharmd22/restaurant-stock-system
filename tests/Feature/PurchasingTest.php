<?php

use App\Enums\RoleName;
use App\Models\BranchItemSetting;
use App\Models\PurchaseOrder;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Services\Purchasing\PurchaseService;

beforeEach(function () {
    seedRoles();

    $this->purchases = app(PurchaseService::class);
    $this->main = mainBranch();
    $this->park = subBranch('PARK');

    $this->onion = kgItem('Onion');
    $this->supplier = Supplier::create(['name' => 'Green Valley', 'is_active' => true]);

    $this->admin = userWithRole(RoleName::MainAdmin, $this->main);
    $this->staff = userWithRole(RoleName::BranchManager, $this->park);
});

it('places an order without moving any stock', function () {
    $this->actingAs($this->admin)
        ->post('/admin/purchase', [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->main->id,
            'lines' => [
                ['item_id' => $this->onion->id, 'qty' => 50, 'unit_price' => 30],
            ],
        ])
        ->assertRedirect();

    $order = PurchaseOrder::withoutBranchScope()->first();

    expect($order->po_number)->toBe('PO-0001')
        ->and($order->status)->toBe('ordered')
        // 50 kg at 30 rupees a kilo.
        ->and((float) $order->total_amount)->toBe(1500.0)
        // A promise is not a delivery.
        ->and(onHand($this->main, $this->onion))->toBe(0);
});

it('turns a price per kilo into a price per gram, so stock values correctly', function () {
    $order = $this->purchases->createOrder($this->supplier->id, $this->main->id, $this->admin, [
        ['item_id' => $this->onion->id, 'qty' => 10, 'unit_price' => 30],
    ]);

    // 30 rupees per kilo is 0.03 per gram.
    expect((float) $order->lines->first()->unit_price)->toBe(0.03)
        ->and($order->lines->first()->qty_ordered)->toBe(10000);
});

it('adds stock only when the goods actually turn up', function () {
    $order = $this->purchases->createOrder($this->supplier->id, $this->main->id, $this->admin, [
        ['item_id' => $this->onion->id, 'qty' => 50, 'unit_price' => 30],
    ]);
    $line = $order->lines->first();

    $this->actingAs($this->admin)
        ->post("/admin/purchase/{$order->id}/receive", ['lines' => [$line->id => 50]])
        ->assertSessionHas('success', 'Added to stock.');

    expect(onHand($this->main, $this->onion))->toBe(50000)
        ->and($order->fresh()->status)->toBe('received');

    $movement = StockLedger::withoutBranchScope()
        ->where('movement_type', 'purchase')
        ->where('reference_type', 'GoodsReceipt')
        ->first();

    expect((float) $movement->unit_cost)->toBe(0.03);
});

it('keeps a record of each separate delivery', function () {
    $order = $this->purchases->createOrder($this->supplier->id, $this->main->id, $this->admin, [
        ['item_id' => $this->onion->id, 'qty' => 50, 'unit_price' => 30],
    ]);
    $line = $order->lines->first();

    $this->purchases->receiveGoods($order, $this->admin, [$line->id => 20]);
    $this->purchases->receiveGoods($order->fresh(['lines.item']), $this->admin, [$line->id => 30]);

    // Two deliveries, two records, two ledger movements - not one swallowed by
    // the idempotency index.
    expect($line->fresh()->receipts)->toHaveCount(2)
        ->and(\App\Models\StockLedger::withoutBranchScope()->where('movement_type', 'purchase')->count())->toBe(2);
});

it('handles a delivery that only half turns up', function () {
    $order = $this->purchases->createOrder($this->supplier->id, $this->main->id, $this->admin, [
        ['item_id' => $this->onion->id, 'qty' => 50, 'unit_price' => 30],
    ]);
    $line = $order->lines->first();

    $this->purchases->receiveGoods($order, $this->admin, [$line->id => 20]);

    expect($order->fresh()->status)->toBe('part_received')
        ->and(onHand($this->main, $this->onion))->toBe(20000)
        ->and($line->fresh()->outstandingBase())->toBe(30000);

    // The rest arrives later.
    $this->purchases->receiveGoods($order->fresh(['lines.item']), $this->admin, [$line->id => 30]);

    expect($order->fresh()->status)->toBe('received')
        ->and(onHand($this->main, $this->onion))->toBe(50000);
});

it('will not accept more than was ordered', function () {
    $order = $this->purchases->createOrder($this->supplier->id, $this->main->id, $this->admin, [
        ['item_id' => $this->onion->id, 'qty' => 10, 'unit_price' => 30],
    ]);
    $line = $order->lines->first();

    $this->actingAs($this->admin)
        ->post("/admin/purchase/{$order->id}/receive", ['lines' => [$line->id => 15]])
        ->assertSessionHas('error');

    expect(onHand($this->main, $this->onion))->toBe(0);
});

it('adds up what every branch is short of', function () {
    // Main store holds 10 kg; two branches want a full shelf of 20 kg each.
    giveStock($this->main, $this->onion, 10);

    $lake = subBranch('LAKE');

    foreach ([$this->park, $lake] as $branch) {
        BranchItemSetting::create([
            'branch_id' => $branch->id,
            'item_id' => $this->onion->id,
            'par_level' => 20000,
            'reorder_level' => 5000,
        ]);
    }

    BranchItemSetting::create([
        'branch_id' => $this->main->id,
        'item_id' => $this->onion->id,
        'par_level' => 30000,
        'reorder_level' => 10000,
    ]);

    $suggestion = $this->purchases->suggestions($this->main->id)->firstWhere('name', 'Onion');

    // Two branches need 20 kg each, the main shelf wants 30 kg, and 10 kg is
    // already here: 20 + 20 + 30 - 10 = 60.
    expect($suggestion['suggested'])->toBe(60.0);
});

it('leaves an item off the buying list when there is plenty', function () {
    giveStock($this->main, $this->onion, 500);

    BranchItemSetting::create([
        'branch_id' => $this->main->id,
        'item_id' => $this->onion->id,
        'par_level' => 20000,
        'reorder_level' => 5000,
    ]);

    expect($this->purchases->suggestions($this->main->id)->firstWhere('name', 'Onion'))->toBeNull();
});

it('keeps branch people out of purchasing', function () {
    $this->actingAs($this->staff)->get('/admin/purchase')->assertForbidden();
    $this->actingAs($this->staff)->get('/admin/suppliers')->assertForbidden();
    $this->actingAs($this->staff)
        ->post('/admin/purchase', [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->main->id,
            'lines' => [['item_id' => $this->onion->id, 'qty' => 1, 'unit_price' => 1]],
        ])
        ->assertForbidden();
});

it('adds a supplier', function () {
    $this->actingAs($this->admin)
        ->post('/admin/suppliers', ['name' => 'Coastal Meat', 'phone' => '9810000009'])
        ->assertSessionHas('success', 'Coastal Meat added.');

    expect(Supplier::where('name', 'Coastal Meat')->exists())->toBeTrue();
});
