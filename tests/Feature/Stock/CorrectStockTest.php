<?php

use App\Enums\MovementType;
use App\Enums\RoleName;
use App\Models\StockLedger;

beforeEach(function () {
    seedRoles();
    $this->main = mainBranch();
    $this->admin = userWithRole(RoleName::MainAdmin, $this->main);
    $this->item = kgItem('Onion');
});

/*
 * Correcting a number is not editing a balance. The balance is the sum of the
 * ledger, so a correction has to be a movement like everything else - which
 * is what these tests are really checking.
 */

it('writes the difference to the ledger rather than overwriting the number', function () {
    giveStock($this->main, $this->item, 10);

    $this->actingAs($this->admin)
        ->post('/admin/stock/correct', [
            'branch_id' => $this->main->id,
            'item_id' => $this->item->id,
            'counted' => 8.5,
            'reason' => 'Counted it and this is what is there',
        ])
        ->assertSessionHas('success');

    $movement = StockLedger::where('item_id', $this->item->id)
        ->where('movement_type', MovementType::Adjustment)
        ->firstOrFail();

    expect($movement->qty_delta)->toBe(-1500)                     // 1.5 kg in grams
        ->and($movement->note)->toBe('Counted it and this is what is there')
        ->and($movement->created_by)->toBe($this->admin->id)
        ->and(onHand($this->main, $this->item))->toBe(8500);
});

it('adds stock when there is more on the shelf than the app thought', function () {
    giveStock($this->main, $this->item, 4);

    $this->actingAs($this->admin)->post('/admin/stock/correct', [
        'branch_id' => $this->main->id,
        'item_id' => $this->item->id,
        'counted' => 6,
        'reason' => 'Found more than the app said',
    ]);

    expect(onHand($this->main, $this->item))->toBe(6000)
        ->and(StockLedger::where('movement_type', MovementType::Adjustment)->value('qty_delta'))->toBe(2000);
});

it('writes nothing when the number was already right', function () {
    giveStock($this->main, $this->item, 5);

    $this->actingAs($this->admin)
        ->post('/admin/stock/correct', [
            'branch_id' => $this->main->id,
            'item_id' => $this->item->id,
            'counted' => 5,
            'reason' => 'Counted it and this is what is there',
        ])
        ->assertSessionHas('info');

    expect(StockLedger::where('movement_type', MovementType::Adjustment)->count())->toBe(0);
});

it('will not let a number change without a reason', function () {
    giveStock($this->main, $this->item, 5);

    $this->actingAs($this->admin)
        ->post('/admin/stock/correct', [
            'branch_id' => $this->main->id,
            'item_id' => $this->item->id,
            'counted' => 3,
        ])
        ->assertSessionHasErrors([
            'reason' => 'Say why the number is changing. Whoever reads this later will need it.',
        ]);

    expect(onHand($this->main, $this->item))->toBe(5000);
});

it('shows the reason in history instead of "Corrected by hand"', function () {
    giveStock($this->main, $this->item, 10);

    $this->actingAs($this->admin)->post('/admin/stock/correct', [
        'branch_id' => $this->main->id,
        'item_id' => $this->item->id,
        'counted' => 9,
        'reason' => 'Spilled or lost',
    ]);

    $movements = app(App\Services\History\HistoryService::class)->movements([], 20);
    $adjustment = collect($movements->items())->firstWhere('type', MovementType::Adjustment->value);

    expect($adjustment['why'])->toBe('Spilled or lost')
        ->and($adjustment['amount'])->toContain('1 kg');
});

it('keeps someone without the stock permission out', function () {
    $staff = userWithRole(RoleName::BranchManager, subBranch());

    $this->actingAs($staff)
        ->post('/admin/stock/correct', [
            'branch_id' => $this->main->id,
            'item_id' => $this->item->id,
            'counted' => 1,
            'reason' => 'Trying it on',
        ])
        ->assertForbidden();
});
