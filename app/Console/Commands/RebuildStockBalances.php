<?php

namespace App\Console\Commands;

use App\Services\Stock\StockLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The ledger is the truth; stock_balances is only a cache of it. If the two
 * ever disagree - a crash mid-transaction, a bad import, a hand-edited row -
 * this command makes the cache match the truth again.
 *
 * It is safe to run at any time, including on a live system.
 */
class RebuildStockBalances extends Command
{
    protected $signature = 'stock:rebuild-balances
                            {--branch= : Only this branch id}
                            {--check : Report differences without changing anything}';

    protected $description = 'Rebuild cached stock balances from the ledger';

    public function handle(StockLedgerService $ledger): int
    {
        $pairs = DB::table('stock_ledger')
            ->select('branch_id', 'item_id')
            ->when($this->option('branch'), fn ($query, $branch) => $query->where('branch_id', $branch))
            ->groupBy('branch_id', 'item_id')
            ->get();

        if ($pairs->isEmpty()) {
            $this->info('No stock movements recorded yet. Nothing to rebuild.');

            return self::SUCCESS;
        }

        $checkOnly = (bool) $this->option('check');
        $differences = 0;

        $bar = $this->output->createProgressBar($pairs->count());
        $bar->start();

        foreach ($pairs as $pair) {
            $truth = (int) DB::table('stock_ledger')
                ->where('branch_id', $pair->branch_id)
                ->where('item_id', $pair->item_id)
                ->sum('qty_delta');

            $cached = (int) (DB::table('stock_balances')
                ->where('branch_id', $pair->branch_id)
                ->where('item_id', $pair->item_id)
                ->value('qty_on_hand') ?? 0);

            if ($truth !== $cached) {
                $differences++;

                if (! $checkOnly) {
                    $ledger->rebuildBalance((int) $pair->branch_id, (int) $pair->item_id);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($differences === 0) {
            $this->info("All {$pairs->count()} balances already match the ledger.");

            return self::SUCCESS;
        }

        $this->warn($checkOnly
            ? "{$differences} balance(s) do not match the ledger. Run without --check to fix them."
            : "Fixed {$differences} balance(s) out of {$pairs->count()}.");

        return self::SUCCESS;
    }
}
