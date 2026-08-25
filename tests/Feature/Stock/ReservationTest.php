<?php

use App\Enums\RoleName;
use App\Exceptions\StockException;
use App\Services\Requests\RequestWorkflowService;
use App\Services\Stock\ReservationService;
use App\Support\Quantity;

beforeEach(function () {
    seedRoles();

    $this->reservations = app(ReservationService::class);
    $this->workflow = app(RequestWorkflowService::class);

    $this->main = mainBranch();
    $this->park = subBranch('PARK');
    $this->lake = subBranch('LAKE');

    $this->onion = kgItem('Onion');
    giveStock($this->main, $this->onion, 25);

    $this->admin = userWithRole(RoleName::MainAdmin, $this->main);
    $this->parkStaff = userWithRole(RoleName::BranchManager, $this->park);
    $this->lakeStaff = userWithRole(RoleName::BranchManager, $this->lake);
});

it('counts reserved stock as spoken for', function () {
    $this->reservations->reserve($this->main->id, Quantity::fromOrderUnit(10, $this->onion));

    expect(onHand($this->main, $this->onion))->toBe(25000)
        ->and(reserved($this->main, $this->onion))->toBe(10000)
        ->and($this->reservations->availableBase($this->main->id, $this->onion->id))->toBe(15000);
});

it('refuses to promise stock that is already promised', function () {
    $this->reservations->reserve($this->main->id, Quantity::fromOrderUnit(20, $this->onion));

    expect(fn () => $this->reservations->reserve($this->main->id, Quantity::fromOrderUnit(10, $this->onion)))
        ->toThrow(StockException::class);

    expect(reserved($this->main, $this->onion))->toBe(20000);
});

/**
 * This is the failure the whole reservation system exists to prevent: two
 * branches told they are getting 20 kg when only 25 kg exists.
 */
it('never promises the same stock to two branches', function () {
    $parkRequest = $this->workflow->submit($this->park, $this->parkStaff, [
        ['item_id' => $this->onion->id, 'qty' => 20],
    ]);
    $lakeRequest = $this->workflow->submit($this->lake, $this->lakeStaff, [
        ['item_id' => $this->onion->id, 'qty' => 20],
    ]);

    $this->workflow->approveAll($parkRequest, $this->admin);

    // The second admin, looking at a screen loaded a minute ago, tries the same.
    expect(fn () => $this->workflow->approveAll($lakeRequest, $this->admin))
        ->toThrow(StockException::class);

    expect(reserved($this->main, $this->onion))->toBe(20000)
        ->and($lakeRequest->fresh()->status->value)->toBe('waiting');
});

it('names the items that are short so the admin knows what to fix', function () {
    $chicken = kgItem('Chicken');
    giveStock($this->main, $chicken, 2);

    $request = $this->workflow->submit($this->park, $this->parkStaff, [
        ['item_id' => $this->onion->id, 'qty' => 5],
        ['item_id' => $chicken->id, 'qty' => 10],
    ]);

    try {
        $this->workflow->approveAll($request, $this->admin);
        $this->fail('Expected the shortage to be refused.');
    } catch (StockException $exception) {
        expect($exception->getMessage())
            ->toContain('Chicken')
            ->not->toContain('Onion');
    }
});

it('lets the admin approve what is actually there', function () {
    $request = $this->workflow->submit($this->park, $this->parkStaff, [
        ['item_id' => $this->onion->id, 'qty' => 40],
    ]);

    $result = $this->workflow->approve($request, $this->admin, [
        $request->lines->first()->id => ['qty' => 25, 'reason_code' => 'out_of_stock'],
    ]);

    expect($result->status->value)->toBe('partial')
        ->and(reserved($this->main, $this->onion))->toBe(25000)
        ->and($this->reservations->availableBase($this->main->id, $this->onion->id))->toBe(0);
});

it('never lets a released promise turn into stock that does not exist', function () {
    $this->reservations->reserve($this->main->id, Quantity::fromOrderUnit(10, $this->onion));

    $this->reservations->release($this->main->id, Quantity::fromOrderUnit(10, $this->onion));
    $this->reservations->release($this->main->id, Quantity::fromOrderUnit(10, $this->onion));

    expect(reserved($this->main, $this->onion))->toBe(0)
        ->and($this->reservations->availableBase($this->main->id, $this->onion->id))->toBe(25000);
});

it('frees the promise again after a cancelled request, for the next branch', function () {
    $parkRequest = $this->workflow->submit($this->park, $this->parkStaff, [
        ['item_id' => $this->onion->id, 'qty' => 20],
    ]);
    $lakeRequest = $this->workflow->submit($this->lake, $this->lakeStaff, [
        ['item_id' => $this->onion->id, 'qty' => 20],
    ]);

    $this->workflow->approveAll($parkRequest, $this->admin);
    $this->workflow->cancel($parkRequest->fresh(), $this->admin, 'Branch closed early');

    // Now the second branch can have it.
    $this->workflow->approveAll($lakeRequest, $this->admin);

    expect(reserved($this->main, $this->onion))->toBe(20000)
        ->and($lakeRequest->fresh()->status->value)->toBe('approved');
});
