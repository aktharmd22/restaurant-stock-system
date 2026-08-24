<?php

use App\Exceptions\StockException;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Services\Stock\StockLedgerService;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->ledger = app(StockLedgerService::class);
    $this->main = mainBranch();
    $this->onion = kgItem('Onion');
});

it('writes one row per movement and keeps the balance in step', function () {
    $this->ledger->purchase($this->main->id, Quantity::fromOrderUnit(25, $this->onion));
    $this->ledger->wastage($this->main->id, Quantity::fromOrderUnit(2, $this->onion));

    expect(StockLedger::withoutBranchScope()->count())->toBe(2)
        ->and(onHand($this->main, $this->onion))->toBe(23000);
});

it('always matches the sum of its own ledger', function () {
    foreach ([10, 5.5, 0.25, 12] as $amount) {
        $this->ledger->purchase($this->main->id, Quantity::fromOrderUnit($amount, $this->onion));
    }
    $this->ledger->transferOut($this->main->id, Quantity::fromOrderUnit(7.75, $this->onion));

    $sum = (int) StockLedger::withoutBranchScope()->sum('qty_delta');

    expect(onHand($this->main, $this->onion))->toBe($sum)->toBe(20000);
});

it('records the running balance on every row', function () {
    $this->ledger->purchase($this->main->id, Quantity::fromOrderUnit(10, $this->onion));
    $this->ledger->purchase($this->main->id, Quantity::fromOrderUnit(5, $this->onion));
    $this->ledger->transferOut($this->main->id, Quantity::fromOrderUnit(3, $this->onion));

    expect(StockLedger::withoutBranchScope()->orderBy('id')->pluck('balance_after')->all())
        ->toBe([10000, 15000, 12000]);
});

it('refuses to take out more than is there', function () {
    $this->ledger->purchase($this->main->id, Quantity::fromOrderUnit(5, $this->onion));

    expect(fn () => $this->ledger->transferOut($this->main->id, Quantity::fromOrderUnit(6, $this->onion)))
        ->toThrow(StockException::class);

    // And nothing was written - the transaction rolled back.
    expect(onHand($this->main, $this->onion))->toBe(5000)
        ->and(StockLedger::withoutBranchScope()->count())->toBe(1);
});

it('explains a shortage in words a person can act on', function () {
    $this->ledger->purchase($this->main->id, Quantity::fromOrderUnit(5, $this->onion));

    try {
        $this->ledger->transferOut($this->main->id, Quantity::fromOrderUnit(6, $this->onion));
    } catch (StockException $exception) {
        expect($exception->getMessage())
            ->toContain('Onion')
            ->toContain('5 kg')
            ->toContain('6 kg');
    }
});

it('does not move stock twice when the same action arrives twice', function () {
    // The double-tap: same reference, same movement, sent twice.
    $reference = $this->onion;

    $first = $this->ledger->purchase($this->main->id, Quantity::fromOrderUnit(10, $this->onion), $reference);
    $second = $this->ledger->purchase($this->main->id, Quantity::fromOrderUnit(10, $this->onion), $reference);

    expect($second->id)->toBe($first->id)
        ->and(StockLedger::withoutBranchScope()->count())->toBe(1)
        ->and(onHand($this->main, $this->onion))->toBe(10000);
});

it('keeps a weighted average cost', function () {
    $this->ledger->purchase($this->main->id, Quantity::fromOrderUnit(10, $this->onion), null, 0.020);
    $this->ledger->purchase($this->main->id, Quantity::fromOrderUnit(10, $this->onion), null, 0.030);

    $balance = StockBalance::withoutBranchScope()->first();

    // 10 kg at 20 paise/g and 10 kg at 30 paise/g averages 25.
    expect((float) $balance->avg_cost)->toBe(0.025);
});

it('lets a stock count push a balance below zero, because the shelf is the shelf', function () {
    $this->ledger->purchase($this->main->id, Quantity::fromOrderUnit(1, $this->onion));

    $this->ledger->adjustment($this->main->id, Quantity::fromOrderUnit(-3, $this->onion));

    expect(onHand($this->main, $this->onion))->toBe(-2000);
});

it('rebuilds a balance that has drifted from the ledger', function () {
    $this->ledger->purchase($this->main->id, Quantity::fromOrderUnit(12, $this->onion));

    // Someone edits the cache by hand. It happens.
    DB::table('stock_balances')->update(['qty_on_hand' => 999]);
    expect(onHand($this->main, $this->onion))->toBe(999);

    $this->artisan('stock:rebuild-balances')->assertSuccessful();

    expect(onHand($this->main, $this->onion))->toBe(12000);
});

it('reports drift without fixing it when asked to check only', function () {
    $this->ledger->purchase($this->main->id, Quantity::fromOrderUnit(12, $this->onion));
    DB::table('stock_balances')->update(['qty_on_hand' => 999]);

    $this->artisan('stock:rebuild-balances --check')->assertSuccessful();

    expect(onHand($this->main, $this->onion))->toBe(999);
});
