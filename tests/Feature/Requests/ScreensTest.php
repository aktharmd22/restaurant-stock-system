<?php

use App\Enums\RoleName;
use App\Models\BranchItemSetting;
use App\Models\StockRequest;
use App\Services\Requests\RequestWorkflowService;

beforeEach(function () {
    seedRoles();

    $this->workflow = app(RequestWorkflowService::class);
    $this->main = mainBranch();
    $this->park = subBranch('PARK');
    $this->lake = subBranch('LAKE');

    $this->onion = kgItem('Onion');
    giveStock($this->main, $this->onion, 100);

    BranchItemSetting::create([
        'branch_id' => $this->park->id,
        'item_id' => $this->onion->id,
        'par_level' => 20000,
        'reorder_level' => 6000,
    ]);

    $this->staff = userWithRole(RoleName::BranchManager, $this->park);
    $this->admin = userWithRole(RoleName::MainAdmin, $this->main);
});

/*
|--------------------------------------------------------------------------
| Branch screens
|--------------------------------------------------------------------------
*/

it('opens the ask screen with a suggested amount already worked out', function () {
    $this->actingAs($this->staff)
        ->get('/b/ask')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Branch/AskForStock')
            ->has('items')
            ->has('cutoff')
            // Nothing on the shelf yet, so the suggestion is the full par level.
            ->where('items.0.suggested', 20));
});

it('sends a request from the screen', function () {
    $this->actingAs($this->staff)
        ->post('/b/ask', [
            'lines' => [['item_id' => $this->onion->id, 'qty' => 12]],
            'note' => 'For the weekend',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Request sent.');

    $request = StockRequest::withoutBranchScope()->first();

    expect($request->lines->first()->qty_requested)->toBe(12000)
        ->and($request->note)->toBe('For the weekend');
});

it('shows the home screen with the countdown and what is running low', function () {
    $this->actingAs($this->staff)
        ->get('/b')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Branch/Home')
            ->has('cutoff.seconds_left')
            ->has('runningLow'));
});

it('keeps one branch out of another branch\'s request', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 5],
    ]);

    $otherStaff = userWithRole(RoleName::BranchManager, $this->lake);

    // The global scope hides it entirely, which is better than confirming it
    // exists and refusing - a 403 would tell them the request is real.
    $this->actingAs($otherStaff)
        ->get("/b/requests/{$request->id}")
        ->assertNotFound();
});

it('lets a branch see its own request with all four numbers', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
    ]);
    $this->workflow->approveAll($request, $this->admin);

    $this->actingAs($this->staff)
        ->get("/b/requests/{$request->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Branch/RequestDetail')
            ->where('request.lines.0.requested_text', '10 kg')
            ->where('request.lines.0.approved_text', '10 kg'));
});

/*
|--------------------------------------------------------------------------
| Admin screens
|--------------------------------------------------------------------------
*/

it('shows the admin free stock beside every line, so they never look it up', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Requests/Inbox')
            ->where('selected.id', $request->id)
            ->where('selected.lines.0.available_text', '100 kg')
            ->where('selected.lines.0.is_short', false));
});

it('flags a line the store cannot cover', function () {
    $chicken = kgItem('Chicken');
    giveStock($this->main, $chicken, 2);

    $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $chicken->id, 'qty' => 10],
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/requests')
        ->assertInertia(fn ($page) => $page->where('selected.lines.0.is_short', true));
});

it('approves from the screen and tells the admin plainly', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
    ]);
    $line = $request->lines->first();

    $this->actingAs($this->admin)
        ->post("/admin/requests/{$request->id}/approve", [
            'decisions' => [$line->id => ['qty' => 6, 'reason_code' => 'out_of_stock']],
        ])
        ->assertSessionHas('success', 'Saved. The branch has been told.');

    expect($request->fresh()->status->value)->toBe('partial')
        ->and(reserved($this->main, $this->onion))->toBe(6000);
});

it('refuses approve-all when the store is short, and says which item', function () {
    $chicken = kgItem('Chicken');
    giveStock($this->main, $chicken, 2);

    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $chicken->id, 'qty' => 10],
    ]);

    $response = $this->actingAs($this->admin)
        ->post("/admin/requests/{$request->id}/approve-all")
        ->assertSessionHas('error');

    expect(session('error'))->toContain('Chicken');
    expect($request->fresh()->status->value)->toBe('waiting');
});

it('shows the pack list grouped by where things are kept', function () {
    $spice = kgItem('Cumin', ['storage_location' => 'Spice rack']);
    giveStock($this->main, $spice, 10);

    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 5],
        ['item_id' => $spice->id, 'qty' => 1],
    ]);
    $this->workflow->approveAll($request, $this->admin);

    $this->actingAs($this->admin)
        ->get("/admin/dispatch/{$request->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dispatch/Pack')
            ->has('packList', 2));
});

it('sends the goods from the dispatch screen', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
    ]);
    $this->workflow->approveAll($request, $this->admin);

    $this->actingAs($this->admin)
        ->post("/admin/dispatch/{$request->id}", [
            'lines' => [],
            'carrier_name' => 'Ravi',
            'vehicle_number' => 'KA-01-AB-1234',
        ])
        ->assertRedirect('/admin/dispatch');

    expect($request->fresh()->status->value)->toBe('sent')
        ->and(onHand($this->main, $this->onion))->toBe(90000);
});

it('confirms a delivery from the branch screen', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
    ]);
    $this->workflow->approveAll($request, $this->admin);
    $this->workflow->dispatch($request->fresh(), $this->admin);
    $line = $request->fresh()->lines->first();

    $this->actingAs($this->staff)
        ->post("/b/receive/{$request->id}", [
            'lines' => [$line->id => ['qty' => 10]],
        ])
        ->assertSessionHas('success', 'Thanks. Stock added to your branch.');

    expect(onHand($this->park, $this->onion))->toBe(10000);
});

/*
|--------------------------------------------------------------------------
| Who is allowed to do what
|--------------------------------------------------------------------------
*/

it('never lets a branch approve its own request', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
    ]);

    $this->actingAs($this->staff)
        ->post("/admin/requests/{$request->id}/approve", [
            'decisions' => [$request->lines->first()->id => ['qty' => 10]],
        ])
        ->assertForbidden();

    expect($request->fresh()->status->value)->toBe('waiting');
});

it('never lets branch staff send goods out of the main store', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
    ]);
    $this->workflow->approveAll($request, $this->admin);

    $this->actingAs($this->staff)
        ->post("/admin/dispatch/{$request->id}", ['lines' => []])
        ->assertForbidden();

    expect(onHand($this->main, $this->onion))->toBe(100000);
});

it('never lets one branch confirm another branch\'s delivery', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
    ]);
    $this->workflow->approveAll($request, $this->admin);
    $this->workflow->dispatch($request->fresh(), $this->admin);

    $otherStaff = userWithRole(RoleName::BranchManager, $this->lake);

    $this->actingAs($otherStaff)
        ->post("/b/receive/{$request->id}", ['lines' => []])
        ->assertNotFound();

    expect(onHand($this->lake, $this->onion))->toBe(0);
});
