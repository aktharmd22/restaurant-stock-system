<?php

use App\Enums\RoleName;
use App\Enums\WastageReason;
use App\Models\BranchItemSetting;
use App\Services\History\HistoryService;
use App\Services\Requests\RequestWorkflowService;
use App\Services\Stock\StockOperationsService;
use App\Support\Quantity;

beforeEach(function () {
    seedRoles();

    $this->history = app(HistoryService::class);
    $this->workflow = app(RequestWorkflowService::class);

    $this->main = mainBranch();
    $this->park = subBranch('PARK');
    $this->onion = kgItem('Onion');

    $this->staff = userWithRole(RoleName::BranchStaff, $this->park);
    $this->admin = userWithRole(RoleName::MainAdmin, $this->main);

    giveStock($this->main, $this->onion, 100, 0.02);
});

/*
|--------------------------------------------------------------------------
| Stock movements
|--------------------------------------------------------------------------
*/

it('shows every movement with the direction, the amount and what was left', function () {
    $rows = $this->history->movements()->items();

    expect($rows)->toHaveCount(1);

    $row = $rows[0];

    expect($row['item'])->toBe('Onion')
        ->and($row['direction'])->toBe('in')
        ->and($row['amount'])->toBe('+100 kg')
        ->and($row['balance_after'])->toBe('100 kg')
        ->and($row['what'])->toBe('Bought');
});

/**
 * The whole point of the module: a row that explains itself. "RequestLine 412"
 * answers nothing; "Request REQ-PARK-0001 from PARK Branch" answers everything.
 */
it('explains a transfer by naming the request that caused it', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
    ]);
    $this->workflow->approveAll($request, $this->admin);
    $this->workflow->dispatch($request->fresh(), $this->admin);

    $out = collect($this->history->movements()->items())
        ->firstWhere('what', 'Went out');

    expect($out['why'])->toBe('Request REQ-PARK-0001 from PARK Branch')
        ->and($out['why_url'])->toContain("selected={$request->id}")
        ->and($out['amount'])->toBe('−10 kg')
        ->and($out['who'])->toBe($this->admin->name);
});

it('explains waste by naming the reason and the note', function () {
    app(StockOperationsService::class)->recordWastage(
        $this->main,
        Quantity::fromOrderUnit(2, $this->onion),
        WastageReason::Spoiled,
        $this->admin,
        'Left out overnight',
    );

    $row = collect($this->history->movements()->items())->firstWhere('what', 'Thrown away');

    expect($row['why'])->toBe('Thrown away: Went bad — Left out overnight')
        ->and($row['direction'])->toBe('out');
});

it('explains a correction as coming from a stock count', function () {
    $count = app(StockOperationsService::class)->openCount(
        $this->main,
        $this->admin,
        collect([$this->onion]),
    );

    app(StockOperationsService::class)->applyCount(
        $count,
        [$count->lines->first()->id => ['counted' => 94]],
        $this->admin,
        'Weighed the cold room',
    );

    $row = collect($this->history->movements()->items())->firstWhere('what', 'Corrected');

    expect($row['why'])->toBe('Stock count: Weighed the cold room')
        ->and($row['amount'])->toBe('−6 kg');
});

it('narrows down by branch, item, type and date', function () {
    $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 5]]);

    expect($this->history->movements(['type' => 'purchase'])->total())->toBe(1)
        ->and($this->history->movements(['type' => 'wastage'])->total())->toBe(0)
        ->and($this->history->movements(['branch' => $this->park->id])->total())->toBe(0)
        ->and($this->history->movements(['search' => 'Onion'])->total())->toBe(1)
        ->and($this->history->movements(['search' => 'Chicken'])->total())->toBe(0)
        ->and($this->history->movements(['from' => now()->addDay()->toDateString()])->total())->toBe(0);
});

it('reads one item at one branch as a running balance', function () {
    giveStock($this->main, $this->onion, 20);

    $rows = $this->history->forItem($this->onion->id, $this->main->id);

    // Newest first: 120 kg after the second delivery, 100 kg after the first.
    expect($rows->pluck('balance_after')->all())->toBe(['120 kg', '100 kg']);
});

/*
|--------------------------------------------------------------------------
| Record changes
|--------------------------------------------------------------------------
*/

it('records who changed a par level, and what it was before', function () {
    $setting = BranchItemSetting::create([
        'branch_id' => $this->park->id,
        'item_id' => $this->onion->id,
        'par_level' => 20000,
        'reorder_level' => 5000,
    ]);

    $this->actingAs($this->admin);
    $setting->update(['par_level' => 30000]);

    $change = collect($this->history->changes(['subject' => 'BranchItemSetting'])->items())->first();

    expect($change['who'])->toBe($this->admin->name)
        ->and($change['what'])->toBe('BranchItemSetting');

    $field = collect($change['fields'])->firstWhere('field', 'Full shelf');

    expect($field['from'])->toBe('20000')->and($field['to'])->toBe('30000');
});

it('never records a password change as a field', function () {
    $this->actingAs($this->admin);
    $this->admin->update(['name' => 'Vikram S', 'password' => 'a-brand-new-password']);

    $change = collect($this->history->changes(['subject' => 'User'])->items())->first();

    $fields = collect($change['fields'])->pluck('field');

    expect($fields)->toContain('Name')
        ->and($fields)->not->toContain('Password');
});

/*
|--------------------------------------------------------------------------
| The screens
|--------------------------------------------------------------------------
*/

it('opens the history screens for the main store', function () {
    $this->actingAs($this->admin)
        ->get('/admin/history')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/History/Index')->has('movements.data'));

    $this->actingAs($this->admin)
        ->get('/admin/history/changes')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/History/Changes'));

    $this->actingAs($this->admin)
        ->get("/admin/history/item/{$this->onion->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/History/Item')
            ->where('item.on_hand', '100 kg'));
});

it('keeps branch people out of the whole-business history', function () {
    $this->actingAs($this->staff)->get('/admin/history')->assertForbidden();
    $this->actingAs($this->staff)->get('/admin/history/changes')->assertForbidden();
});
