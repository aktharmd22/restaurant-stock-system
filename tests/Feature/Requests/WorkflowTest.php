<?php

use App\Enums\LineStatus;
use App\Enums\RequestStatus;
use App\Enums\RoleName;
use App\Exceptions\StockException;
use App\Models\ReceiptDiscrepancy;
use App\Models\StockLedger;
use App\Services\Requests\RequestWorkflowService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    seedRoles();

    $this->workflow = app(RequestWorkflowService::class);
    $this->main = mainBranch();
    $this->park = subBranch('PARK');

    $this->onion = kgItem('Onion');
    $this->chicken = kgItem('Chicken');

    giveStock($this->main, $this->onion, 100);
    giveStock($this->main, $this->chicken, 50);

    $this->staff = userWithRole(RoleName::BranchStaff, $this->park);
    $this->admin = userWithRole(RoleName::MainAdmin, $this->main);
});

/*
|--------------------------------------------------------------------------
| Asking
|--------------------------------------------------------------------------
*/

it('sends a request with a readable number', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
        ['item_id' => $this->chicken->id, 'qty' => 5],
    ]);

    expect($request->request_number)->toBe('REQ-PARK-0001')
        ->and($request->status)->toBe(RequestStatus::Waiting)
        ->and($request->lines)->toHaveCount(2)
        ->and($request->lines->firstWhere('item_id', $this->onion->id)->qty_requested)->toBe(10000);
});

it('numbers each branch separately', function () {
    $lake = subBranch('LAKE');
    $lakeStaff = userWithRole(RoleName::BranchStaff, $lake);

    $first = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 1]]);
    $second = $this->workflow->submit($lake, $lakeStaff, [['item_id' => $this->onion->id, 'qty' => 1]]);
    $third = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 1]]);

    expect($first->request_number)->toBe('REQ-PARK-0001')
        ->and($second->request_number)->toBe('REQ-LAKE-0001')
        ->and($third->request_number)->toBe('REQ-PARK-0002');
});

it('adds up the same item asked for twice', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 3],
        ['item_id' => $this->onion->id, 'qty' => 2],
    ]);

    expect($request->lines)->toHaveCount(1)
        ->and($request->lines->first()->qty_requested)->toBe(5000);
});

it('ignores lines left at zero', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 4],
        ['item_id' => $this->chicken->id, 'qty' => 0],
    ]);

    expect($request->lines)->toHaveCount(1);
});

it('will not send an empty request', function () {
    expect(fn () => $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 0],
    ]))->toThrow(StockException::class, 'Add at least one item before sending.');
});

it('marks a request late when it is sent after the cut-off, but still sends it', function () {
    // Cut-off is 18:00 for this branch.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 19:30', 'Asia/Kolkata'));

    $late = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 1]]);

    expect($late->is_late)->toBeTrue()
        ->and($late->status)->toBe(RequestStatus::Waiting);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 11:00', 'Asia/Kolkata'));

    $onTime = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 1]]);

    expect($onTime->is_late)->toBeFalse();

    CarbonImmutable::setTestNow();
});

it('lets a branch ask as many times a day as it needs', function () {
    foreach (range(1, 4) as $ignored) {
        $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 1]]);
    }

    expect($this->park->id)->not->toBeNull();
    expect(\App\Models\StockRequest::withoutBranchScope()->count())->toBe(4);
});

/*
|--------------------------------------------------------------------------
| Approving
|--------------------------------------------------------------------------
*/

it('approves everything in one click and reserves the stock', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
        ['item_id' => $this->chicken->id, 'qty' => 5],
    ]);

    $approved = $this->workflow->approveAll($request, $this->admin);

    expect($approved->status)->toBe(RequestStatus::Approved)
        ->and(reserved($this->main, $this->onion))->toBe(10000)
        ->and(reserved($this->main, $this->chicken))->toBe(5000)
        // Nothing has physically moved yet.
        ->and(onHand($this->main, $this->onion))->toBe(100000);
});

it('is partial when one line is cut', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
        ['item_id' => $this->chicken->id, 'qty' => 5],
    ]);

    $onionLine = $request->lines->firstWhere('item_id', $this->onion->id);
    $chickenLine = $request->lines->firstWhere('item_id', $this->chicken->id);

    $result = $this->workflow->approve($request, $this->admin, [
        $onionLine->id => ['qty' => 6, 'reason_code' => 'out_of_stock'],
        $chickenLine->id => ['qty' => 5],
    ]);

    expect($result->status)->toBe(RequestStatus::Partial)
        ->and($result->lines->firstWhere('id', $onionLine->id)->line_status)->toBe(LineStatus::Reduced)
        ->and(reserved($this->main, $this->onion))->toBe(6000);
});

it('is rejected when every line is refused', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
    ]);

    $result = $this->workflow->approve($request, $this->admin, [
        $request->lines->first()->id => ['qty' => 0, 'reason_code' => 'not_needed_today'],
    ]);

    expect($result->status)->toBe(RequestStatus::Rejected)
        ->and(reserved($this->main, $this->onion))->toBe(0);
});

it('insists on a reason before cutting a line', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
    ]);

    expect(fn () => $this->workflow->approve($request, $this->admin, [
        $request->lines->first()->id => ['qty' => 4],
    ]))->toThrow(StockException::class, 'Choose a reason for Onion.');
});

it('will not approve more than was asked for', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [
        ['item_id' => $this->onion->id, 'qty' => 10],
    ]);

    expect(fn () => $this->workflow->approve($request, $this->admin, [
        $request->lines->first()->id => ['qty' => 15],
    ]))->toThrow(StockException::class);
});

it('will not look at the same request twice', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 1]]);
    $this->workflow->approveAll($request, $this->admin);

    expect(fn () => $this->workflow->approveAll($request->fresh(), $this->admin))
        ->toThrow(StockException::class, 'This request has already been looked at.');
});

/*
|--------------------------------------------------------------------------
| Sending
|--------------------------------------------------------------------------
*/

it('moves stock out of the main store only when it is sent', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 10]]);
    $this->workflow->approveAll($request, $this->admin);

    expect(onHand($this->main, $this->onion))->toBe(100000);

    $sent = $this->workflow->dispatch($request->fresh(), $this->admin, [], 'Ravi', 'KA-01-AB-1234');

    expect($sent->status)->toBe(RequestStatus::Sent)
        ->and(onHand($this->main, $this->onion))->toBe(90000)
        ->and(reserved($this->main, $this->onion))->toBe(0)
        // It has left the main store but not arrived anywhere yet.
        ->and(onHand($this->park, $this->onion))->toBe(0)
        ->and($sent->dispatchNote->note_number)->toBe('DN-0001')
        ->and($sent->dispatchNote->carrier_name)->toBe('Ravi');
});

it('will not send more than was approved', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 10]]);
    $this->workflow->approveAll($request, $this->admin);
    $line = $request->fresh()->lines->first();

    expect(fn () => $this->workflow->dispatch($request->fresh(), $this->admin, [$line->id => 12]))
        ->toThrow(StockException::class);
});

it('releases the whole promise even when less is sent', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 10]]);
    $this->workflow->approveAll($request, $this->admin);
    $line = $request->fresh()->lines->first();

    $this->workflow->dispatch($request->fresh(), $this->admin, [$line->id => 7]);

    expect(reserved($this->main, $this->onion))->toBe(0)
        ->and(onHand($this->main, $this->onion))->toBe(93000);
});

/*
|--------------------------------------------------------------------------
| Receiving
|--------------------------------------------------------------------------
*/

it('adds stock to the branch only when it is confirmed', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 10]]);
    $this->workflow->approveAll($request, $this->admin);
    $this->workflow->dispatch($request->fresh(), $this->admin);

    $received = $this->workflow->receive($request->fresh(), $this->staff);

    expect($received->status)->toBe(RequestStatus::Received)
        ->and(onHand($this->park, $this->onion))->toBe(10000)
        ->and(StockLedger::withoutBranchScope()->where('branch_id', $this->park->id)->count())->toBe(1);
});

it('records what happened when less arrives than was sent', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 10]]);
    $this->workflow->approveAll($request, $this->admin);
    $this->workflow->dispatch($request->fresh(), $this->admin);
    $line = $request->fresh()->lines->first();

    $this->workflow->receive($request->fresh(), $this->staff, [
        $line->id => ['qty' => 8, 'reason' => 'damaged', 'note' => 'Two bags split'],
    ]);

    $discrepancy = ReceiptDiscrepancy::first();

    expect(onHand($this->park, $this->onion))->toBe(8000)
        ->and($discrepancy->qty_short)->toBe(2000)
        ->and($discrepancy->reason->value)->toBe('damaged')
        ->and($discrepancy->note)->toBe('Two bags split');
});

it('will not accept a short delivery without saying what happened', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 10]]);
    $this->workflow->approveAll($request, $this->admin);
    $this->workflow->dispatch($request->fresh(), $this->admin);
    $line = $request->fresh()->lines->first();

    expect(fn () => $this->workflow->receive($request->fresh(), $this->staff, [
        $line->id => ['qty' => 8],
    ]))->toThrow(StockException::class, 'Say what happened to the missing Onion.');
});

it('will not let more arrive than was sent', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 10]]);
    $this->workflow->approveAll($request, $this->admin);
    $this->workflow->dispatch($request->fresh(), $this->admin);
    $line = $request->fresh()->lines->first();

    expect(fn () => $this->workflow->receive($request->fresh(), $this->staff, [$line->id => ['qty' => 11]]))
        ->toThrow(StockException::class);
});

it('keeps all four numbers so the gaps can be seen later', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 10]]);
    $line = $request->lines->first();

    $this->workflow->approve($request, $this->admin, [$line->id => ['qty' => 8, 'reason_code' => 'out_of_stock']]);
    $this->workflow->dispatch($request->fresh(), $this->admin, [$line->id => 7]);
    $this->workflow->receive($request->fresh(), $this->staff, [$line->id => ['qty' => 6, 'reason' => 'missing']]);

    $line = $line->fresh();

    expect([$line->qty_requested, $line->qty_approved, $line->qty_sent, $line->qty_received])
        ->toBe([10000, 8000, 7000, 6000]);
});

/*
|--------------------------------------------------------------------------
| Cancelling
|--------------------------------------------------------------------------
*/

it('gives the stock back when an approved request is cancelled', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 10]]);
    $this->workflow->approveAll($request, $this->admin);

    expect(reserved($this->main, $this->onion))->toBe(10000);

    $this->workflow->cancel($request->fresh(), $this->staff, 'Not needed after all');

    expect(reserved($this->main, $this->onion))->toBe(0)
        ->and($request->fresh()->status)->toBe(RequestStatus::Cancelled);
});

it('cannot cancel something already on the van', function () {
    $request = $this->workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 10]]);
    $this->workflow->approveAll($request, $this->admin);
    $this->workflow->dispatch($request->fresh(), $this->admin);

    expect(fn () => $this->workflow->cancel($request->fresh(), $this->staff))
        ->toThrow(StockException::class);
});
