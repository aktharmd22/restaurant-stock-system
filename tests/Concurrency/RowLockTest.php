<?php

use App\Services\Stock\ReservationService;
use App\Support\Quantity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Everything else in the suite proves the availability CHECK is right. This
 * file proves the LOCK is real - that reserve() genuinely holds the balance
 * row, so two requests arriving at the same instant cannot both read "25 kg
 * free" and both succeed.
 *
 * It needs a second, independent database connection, which is why this suite
 * runs on committed data instead of inside a transaction.
 */
beforeEach(function () {
    $this->main = mainBranch();
    $this->onion = kgItem('Onion');
    giveStock($this->main, $this->onion, 25);

    // A second connection, standing in for a second web request.
    Config::set('database.connections.second', config('database.connections.mysql'));
    DB::purge('second');
    $this->second = DB::connection('second');
});

afterEach(function () {
    try {
        $this->second->rollBack();
    } catch (Throwable) {
        // Already finished - nothing to undo.
    }

    DB::purge('second');
});

it('holds the balance row so a second request has to wait its turn', function () {
    // Fail fast rather than hanging the suite for 50 seconds.
    DB::statement('SET SESSION innodb_lock_wait_timeout = 2');

    // The other "request" grabs the row first and keeps it.
    $this->second->beginTransaction();
    $this->second->table('stock_balances')
        ->where('branch_id', $this->main->id)
        ->where('item_id', $this->onion->id)
        ->lockForUpdate()
        ->first();

    $blocked = false;

    try {
        app(ReservationService::class)->reserve(
            $this->main->id,
            Quantity::fromOrderUnit(10, $this->onion),
        );
    } catch (QueryException $exception) {
        // MySQL 1205 / MariaDB: lock wait timeout exceeded.
        $blocked = str_contains(strtolower($exception->getMessage()), 'lock wait timeout');
    }

    expect($blocked)->toBeTrue(
        'reserve() did not wait for the row lock, so two admins could promise the same stock.',
    );

    $this->second->rollBack();

    // With the lock gone it goes through normally.
    app(ReservationService::class)->reserve($this->main->id, Quantity::fromOrderUnit(10, $this->onion));

    expect(reserved($this->main, $this->onion))->toBe(10000);
});

it('leaves nothing behind when a reservation is refused', function () {
    $service = app(ReservationService::class);

    $service->reserve($this->main->id, Quantity::fromOrderUnit(25, $this->onion));

    try {
        $service->reserve($this->main->id, Quantity::fromOrderUnit(1, $this->onion));
    } catch (Throwable) {
        // Expected.
    }

    // A failed reservation must not have half-written anything.
    expect(reserved($this->main, $this->onion))->toBe(25000)
        ->and(onHand($this->main, $this->onion))->toBe(25000);
});
