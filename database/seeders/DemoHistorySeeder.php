<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\RequestStatus;
use App\Enums\WastageReason;
use App\Models\Branch;
use App\Models\BranchItemSetting;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\LocalPurchase;
use App\Models\StockRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Requests\RequestWorkflowService;
use App\Services\Purchasing\PurchaseService;
use App\Services\Stock\StockLedgerService;
use App\Services\Stock\StockOperationsService;
use App\Support\Quantity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Thirty days of believable history, built by running the real workflow rather
 * than inserting rows. That means the ledger, the balances, the reservations
 * and the four quantities are all genuinely consistent - and demoing the app
 * exercises the same code the restaurant will.
 */
class DemoHistorySeeder extends Seeder
{
    private const DAYS = 30;

    private int $skippedDays = 0;

    public function run(): void
    {
        $workflow = app(RequestWorkflowService::class);
        $ledger = app(StockLedgerService::class);

        $main = Branch::where('code', 'MAIN')->firstOrFail();
        $branches = Branch::sub()->active()->get();
        $items = Item::active()->get();

        $storeKeeper = User::where('email', 'store@demo.test')->firstOrFail();

        $start = CarbonImmutable::now()->subDays(self::DAYS)->setTime(8, 0);

        // Stock the main store, twice its own par level, a month ago.
        Carbon::withTestNow($start->subDay(), function () use ($items, $main, $ledger) {
            foreach ($items as $item) {
                $par = BranchItemSetting::where('branch_id', $main->id)
                    ->where('item_id', $item->id)
                    ->value('par_level') ?? 0;

                if ($par <= 0) {
                    continue;
                }

                $ledger->purchase(
                    $main->id,
                    Quantity::fromBase((int) ($par * 2), $item),
                    null,
                    $this->priceFor($item),
                );
            }
        });

        foreach (range(self::DAYS, 0) as $daysAgo) {
            $day = CarbonImmutable::now()->subDays($daysAgo);

            // The main store buys in once a week, like a real one does.
            if ($daysAgo % 5 === 0) {
                $this->restockMain($ledger, $main, $items, $day->setTime(7, 0));
            }

            foreach ($branches as $branch) {
                // Not every branch orders every day.
                if (random_int(1, 10) <= 2) {
                    continue;
                }

                $this->runOneDay($workflow, $ledger, $branch, $storeKeeper, $items, $day, $daysAgo);
            }
        }

        $this->addWasteAndLocalBuying($branches, $items);
        $this->addSupplierOrders($main, $items, $storeKeeper);
        $this->closeStaleRequests($workflow, $storeKeeper);

        if ($this->skippedDays > 0) {
            $this->command?->info("Skipped {$this->skippedDays} branch-days where the store ran short.");
        }
    }

    /**
     * Kitchens throw things away and buy things in a hurry. Without either,
     * the waste report and the local-buying screen demo as empty boxes.
     */
    private function addWasteAndLocalBuying($branches, $items): void
    {
        $operations = app(StockOperationsService::class);
        $reasons = WastageReason::cases();

        foreach ($branches as $branch) {
            $staff = User::where('branch_id', $branch->id)->first();

            if (! $staff) {
                continue;
            }

            foreach (range(self::DAYS - 2, 1) as $daysAgo) {
                // Roughly twice a week per branch.
                if (random_int(1, 7) > 2) {
                    continue;
                }

                $item = $items->random();

                $onHand = (int) (StockBalance::withoutBranchScope()
                    ->where('branch_id', $branch->id)
                    ->where('item_id', $item->id)
                    ->value('qty_on_hand') ?? 0);

                if ($onHand < $item->conversion_factor) {
                    continue;
                }

                // Never more than a tenth of what is there.
                $qty = max(1, (int) round($onHand * (random_int(2, 10) / 100)));

                try {
                    Carbon::withTestNow(
                        CarbonImmutable::now()->subDays($daysAgo)->setTime(random_int(10, 22), random_int(0, 59)),
                        fn () => $operations->recordWastage(
                            $branch,
                            Quantity::fromBase($qty, $item),
                            $reasons[array_rand($reasons)],
                            $staff,
                        ),
                    );
                } catch (Throwable) {
                    // Not enough left by the time we got here. Skip it.
                }
            }

            // A couple of emergency buys per branch, in every state.
            foreach ([['approved', 6], ['rejected', 12], ['waiting', 2]] as [$state, $daysAgo]) {
                $item = $items->random();
                $qty = max(1, $item->conversion_factor * random_int(1, 4));

                $purchase = Carbon::withTestNow(
                    CarbonImmutable::now()->subDays($daysAgo)->setTime(13, 30),
                    fn () => LocalPurchase::create([
                        'branch_id' => $branch->id,
                        'item_id' => $item->id,
                        'qty' => $qty,
                        'amount' => round($qty * $this->priceFor($item) * 1.2, 2),
                        'reason' => collect([
                            'Ran out during lunch service',
                            'Main store was closed',
                            'Delivery did not arrive',
                        ])->random(),
                        'status' => 'waiting',
                        'requested_by' => $staff->id,
                    ]),
                );

                $admin = User::where('email', 'store@demo.test')->first();

                if ($state === 'approved') {
                    Carbon::withTestNow(
                        CarbonImmutable::now()->subDays($daysAgo)->setTime(18, 0),
                        fn () => $operations->approveLocalPurchase($purchase, $admin),
                    );
                }

                if ($state === 'rejected') {
                    Carbon::withTestNow(
                        CarbonImmutable::now()->subDays($daysAgo)->setTime(18, 0),
                        fn () => $operations->rejectLocalPurchase($purchase, $admin, 'Ask us first next time'),
                    );
                }
            }
        }
    }

    /**
     * A few supplier orders so the purchase screens and the price report have
     * something real in them.
     */
    private function addSupplierOrders($main, $items, User $storeKeeper): void
    {
        $purchases = app(PurchaseService::class);
        $suppliers = Supplier::active()->get();

        if ($suppliers->isEmpty()) {
            return;
        }

        foreach ([['received', 20], ['part', 8], ['open', 2]] as [$state, $daysAgo]) {
            $lines = $items->random(6)->map(fn (Item $item) => [
                'item_id' => $item->id,
                'qty' => random_int(2, 20),
                'unit_price' => round($this->priceFor($item) * $item->conversion_factor, 2),
            ])->all();

            $order = Carbon::withTestNow(
                CarbonImmutable::now()->subDays($daysAgo)->setTime(9, 0),
                fn () => $purchases->createOrder(
                    $suppliers->random()->id,
                    $main->id,
                    $storeKeeper,
                    $lines,
                    CarbonImmutable::now()->subDays($daysAgo - 2)->toDateString(),
                ),
            );

            if ($state === 'open') {
                continue;
            }

            $received = $order->lines
                ->take($state === 'received' ? 6 : 3)
                ->mapWithKeys(fn ($line) => [$line->id => $line->ordered()->toOrderUnit()])
                ->all();

            Carbon::withTestNow(
                CarbonImmutable::now()->subDays($daysAgo - 2)->setTime(11, 0),
                fn () => $purchases->receiveGoods($order->fresh(['lines.item']), $storeKeeper, $received),
            );
        }
    }

    /**
     * A request the store could not cover was left waiting. Leaving a
     * three-week-old request sitting in the admin inbox would just look like a
     * bug in the demo, so those are cancelled with an honest reason. Today's
     * requests stay waiting - the demo should open with real work to do.
     */
    private function closeStaleRequests(RequestWorkflowService $workflow, User $storeKeeper): void
    {
        $stale = StockRequest::withoutBranchScope()
            ->where('status', RequestStatus::Waiting)
            ->where('submitted_at', '<', CarbonImmutable::now()->startOfDay())
            ->get();

        foreach ($stale as $request) {
            Carbon::withTestNow(
                $request->submitted_at->addHours(4),
                fn () => $workflow->cancel($request, $storeKeeper, 'Main store was out of stock'),
            );
        }
    }

    private function runOneDay(
        RequestWorkflowService $workflow,
        StockLedgerService $ledger,
        Branch $branch,
        User $storeKeeper,
        $items,
        CarbonImmutable $day,
        int $daysAgo,
    ): void {
        $staff = User::where('branch_id', $branch->id)->inRandomOrder()->first();

        if (! $staff) {
            return;
        }

        $chosen = $items->random(random_int(5, 10));

        $lines = $chosen->map(fn (Item $item) => [
            'item_id' => $item->id,
            'qty' => $this->believableQuantity($item),
        ])->all();

        try {
            // Most requests go in before the cut-off; a few slip past it.
            $sentAt = $day->setTime(random_int(1, 10) <= 8 ? random_int(9, 16) : 19, random_int(0, 59));

            $request = Carbon::withTestNow($sentAt, fn () => $workflow->submit($branch, $staff, $lines));

            // Today's requests are left waiting, so the demo opens with work to do.
            if ($daysAgo === 0) {
                return;
            }

            $reviewedAt = $sentAt->addMinutes(random_int(20, 180));
            $roll = random_int(1, 10);

            Carbon::withTestNow($reviewedAt, function () use ($workflow, $request, $storeKeeper, $roll) {
                if ($roll <= 7) {
                    $workflow->approveAll($request, $storeKeeper);

                    return;
                }

                // Some days the store is short and lines get cut.
                $decisions = [];

                foreach ($request->lines()->with('item')->get() as $index => $line) {
                    $full = $line->requested()->toOrderUnit();

                    $decisions[$line->id] = match (true) {
                        $index === 0 && $roll === 10 => ['qty' => 0, 'reason_code' => 'out_of_stock'],
                        $index === 0 => ['qty' => round($full / 2, 2), 'reason_code' => 'too_much_asked'],
                        default => ['qty' => $full],
                    };
                }

                $workflow->approve($request, $storeKeeper, $decisions);
            });

            // Yesterday's approved requests are left to pack.
            if ($daysAgo <= 1) {
                return;
            }

            $dispatchedAt = $reviewedAt->addHours(random_int(1, 6));

            Carbon::withTestNow($dispatchedAt, fn () => $workflow->dispatch(
                $request->fresh(),
                $storeKeeper,
                [],
                collect(['Ravi', 'Suresh', 'Manoj', 'Own van'])->random(),
                'KA-01-'.collect(['AB', 'CD', 'EF'])->random().'-'.random_int(1000, 9999),
            ));

            // Requests from two days ago are still on the van.
            if ($daysAgo <= 2) {
                return;
            }

            $receivedAt = $dispatchedAt->addHours(random_int(2, 20));

            Carbon::withTestNow($receivedAt, function () use ($workflow, $request, $staff) {
                $received = [];

                foreach ($request->fresh()->lines()->with('item')->get() as $line) {
                    $sent = $line->sent()->toOrderUnit();

                    // Roughly one delivery in eight arrives short.
                    $received[$line->id] = random_int(1, 8) === 1 && $sent > 0
                        ? ['qty' => round($sent * 0.8, 2), 'reason' => collect(['damaged', 'missing', 'expired'])->random()]
                        : ['qty' => $sent];
                }

                $workflow->receive($request->fresh(), $staff, $received);
            });

            // Branches use what they receive. Without this, branch stock would
            // climb forever and "8 kg left here" would become fiction.
            Carbon::withTestNow($receivedAt->addHours(12), function () use ($request, $branch, $ledger) {
                foreach ($request->fresh()->lines()->with('item')->get() as $line) {
                    $used = (int) round($line->qty_received * (random_int(60, 90) / 100));

                    if ($used > 0) {
                        $ledger->consumption($branch->id, Quantity::fromBase($used, $line->item));
                    }
                }
            });
        } catch (Throwable) {
            // A day the main store could not cover is simply skipped. The demo
            // does not need every single day, and a half-written one would be
            // worse than a missing one.
            $this->skippedDays++;
        }
    }

    /**
     * Top the main store back up to twice its par level. Without this the
     * store simply runs dry over thirty days and the demo fills up with
     * requests nobody could approve.
     */
    private function restockMain(StockLedgerService $ledger, Branch $main, $items, CarbonImmutable $at): void
    {
        Carbon::withTestNow($at, function () use ($items, $main, $ledger) {
            foreach ($items as $item) {
                $par = (int) (BranchItemSetting::where('branch_id', $main->id)
                    ->where('item_id', $item->id)
                    ->value('par_level') ?? 0);

                if ($par <= 0) {
                    continue;
                }

                $onHand = (int) (StockBalance::withoutBranchScope()
                    ->where('branch_id', $main->id)
                    ->where('item_id', $item->id)
                    ->value('qty_on_hand') ?? 0);

                $topUp = ($par * 3) - $onHand;

                if ($topUp > 0) {
                    $ledger->purchase($main->id, Quantity::fromBase($topUp, $item), null, $this->priceFor($item));
                }
            }
        });
    }

    /** Ask for something in the right ballpark for the unit. */
    private function believableQuantity(Item $item): float
    {
        $step = max(0.25, $item->stepSize());
        $steps = random_int(2, 6);

        return round($step * $steps, 2);
    }

    /** A rough price per BASE unit, so report totals look sane. */
    private function priceFor(Item $item): float
    {
        return match ($item->base_unit) {
            'g' => round(random_int(2, 60) / 100, 4),   // paise per gram
            'ml' => round(random_int(5, 20) / 100, 4),
            default => (float) random_int(4, 90),        // per piece
        };
    }
}
