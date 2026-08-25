<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\StockException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Services\Stock\StockLedgerService;
use App\Services\Stock\StockOperationsService;
use App\Services\Stock\StockViewService;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function __construct(private readonly StockOperationsService $operations)
    {
    }

    public function index(Request $request, StockViewService $stock): Response
    {
        $branch = $this->pickBranch($request);

        $rows = $stock->forBranch(
            $branch->id,
            $request->string('search')->trim()->value() ?: null,
            $request->integer('category') ?: null,
        );

        // "Show me only what is running low" is the question this screen gets
        // asked most, so it is a filter rather than something to hunt for.
        $show = $request->string('show')->value() ?: 'all';

        if ($show === 'low') {
            $rows = $rows->where('is_low', true)->values();
        } elseif ($show === 'none') {
            $rows = $rows->filter(fn ($row) => ($row['on_hand'] ?? 0) <= 0)->values();
        }

        return Inertia::render('Admin/Stock/Index', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name, 'is_main' => $branch->isMain()],
            'branches' => Branch::active()
                ->orderByRaw("FIELD(type, 'main', 'sub')")
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => Category::active()->ordered()->get(['id', 'name']),
            'rows' => $rows,
            'totals' => [
                'items' => $rows->count(),
                'low' => $rows->where('is_low', true)->count(),
                'value' => round($rows->sum('value'), 2),
            ],
            'filters' => [
                'branch' => $branch->id,
                'search' => $request->string('search')->value(),
                'category' => $request->integer('category') ?: null,
                'show' => $show,
            ],
            'canAdjust' => $request->user()->can('stock.adjust'),
            'openCount' => StockCount::withoutBranchScope()
                ->where('branch_id', $branch->id)
                ->where('status', 'open')
                ->value('id'),
        ]);
    }

    /**
     * Correct one number on the spot.
     *
     * You never edit a balance: you say what is really on the shelf, and the
     * difference is written to the ledger as a movement with a reason on it.
     * That way the number and the story behind it can never disagree, which is
     * the whole point of keeping a ledger in the first place.
     */
    public function correct(Request $request, StockLedgerService $ledger): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'item_id' => ['required', 'exists:items,id'],
            'counted' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'reason' => ['required', 'string', 'max:160'],
        ], [
            'counted.required' => 'Say how much is actually there.',
            'reason.required' => 'Say why the number is changing. Whoever reads this later will need it.',
        ]);

        $item = Item::findOrFail($validated['item_id']);
        $branch = Branch::findOrFail($validated['branch_id']);

        $onHand = (int) (StockBalance::withoutBranchScope()
            ->where('branch_id', $branch->id)
            ->where('item_id', $item->id)
            ->value('qty_on_hand') ?? 0);

        $counted = Quantity::fromOrderUnit($validated['counted'], $item);
        $delta = $counted->baseUnits - $onHand;

        if ($delta === 0) {
            return back()->with('info', "{$item->name} was already right. Nothing changed.");
        }

        $ledger->adjustment(
            $branch->id,
            Quantity::fromBase($delta, $item),
            null,
            $request->user(),
            $validated['reason'],
        );

        $movement = $item->quantity(abs($delta))->forDisplay();
        $direction = $delta > 0 ? 'added to' : 'taken off';

        return back()->with(
            'success',
            "{$item->name} at {$branch->name} is now {$counted->forDisplay()}. {$movement} {$direction} the shelf.",
        );
    }

    /**
     * Start counting. Every line remembers what the system thought at the
     * moment counting began.
     */
    public function startCount(Request $request): RedirectResponse
    {
        $branch = $this->pickBranch($request);

        $existing = StockCount::withoutBranchScope()
            ->where('branch_id', $branch->id)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return redirect()->route('admin.stock.count', $existing->id);
        }

        $count = $this->operations->openCount(
            $branch,
            $request->user(),
            Item::active()->ordered()->get(),
        );

        return redirect()->route('admin.stock.count', $count->id);
    }

    public function count(StockCount $stockCount): Response
    {
        $stockCount->load(['lines.item', 'branch']);

        return Inertia::render('Admin/Stock/Count', [
            'count' => [
                'id' => $stockCount->id,
                'branch' => $stockCount->branch->name,
                'status' => $stockCount->status,
                'lines' => $stockCount->lines
                    ->sortBy(fn ($line) => $line->item->name)
                    ->map(fn ($line) => [
                        'id' => $line->id,
                        'item' => $line->item->name,
                        'unit' => $line->item->quantity(0)->unitLabel(),
                        'step' => $line->item->stepSize(),
                        'decimals' => $line->item->decimals(),
                        'storage_location' => $line->item->storage_location,
                        'system' => $line->item->quantity($line->system_qty)->toOrderUnit(),
                        'system_text' => $line->item->quantity($line->system_qty)->forDisplay(),
                        'counted' => $line->item->quantity($line->counted_qty)->toOrderUnit(),
                    ])->values(),
            ],
        ]);
    }

    public function applyCount(Request $request, StockCount $stockCount): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.counted' => ['required', 'numeric', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
            'note' => ['required', 'string', 'max:255'],
        ], [
            // A correction with no explanation is how stock records stop being
            // believed.
            'note.required' => 'Say why the numbers are different before applying the count.',
        ]);

        try {
            $this->operations->applyCount($stockCount, $validated['lines'], $request->user(), $validated['note']);
        } catch (StockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.stock.index', ['branch' => $stockCount->branch_id])
            ->with('success', 'Count applied. The books now match the shelf.');
    }

    private function pickBranch(Request $request): Branch
    {
        $id = $request->integer('branch');

        return ($id ? Branch::find($id) : null)
            ?? Branch::main()
            ?? Branch::active()->firstOrFail();
    }
}
