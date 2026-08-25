<?php

namespace App\Http\Controllers;

use App\Enums\WastageReason;
use App\Exceptions\StockException;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Wastage;
use App\Services\Stock\StockOperationsService;
use App\Services\Stock\StockViewService;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recording what got thrown away. Used by both sides - the branch scope means
 * a branch sees only its own, and the admin sees everything.
 */
class WastageController extends Controller
{
    public function __construct(private readonly StockOperationsService $operations)
    {
    }

    public function index(Request $request, StockViewService $stock): Response
    {
        $user = $request->user();
        $branchId = $user->isAdminSide()
            ? ($request->integer('branch') ?: Branch::main()?->id)
            : $user->branch_id;

        $search = $request->string('search')->trim()->value();
        $reason = $request->string('reason')->value();

        $recent = Wastage::with(['item', 'recordedBy', 'branch'])
            ->when($user->isAdminSide() && $branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($search, fn ($query, string $term) => $query->whereHas(
                'item',
                fn ($item) => $item->where('name', 'like', "%{$term}%"),
            ))
            ->when($reason, fn ($query, string $value) => $query->where('reason', $value))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Wastage $wastage) => [
                'id' => $wastage->id,
                'item' => $wastage->item->name,
                'qty_text' => $wastage->quantity()->forDisplay(),
                'reason' => $wastage->reason->label(),
                'note' => $wastage->note,
                'branch' => $wastage->branch?->name,
                'by' => $wastage->recordedBy?->firstName(),
                'when' => $wastage->created_at->format('D j M, g:i a'),
                'photo' => $wastage->photoUrl(),
            ]);

        return Inertia::render('Waste/Index', [
            'entries' => $recent,
            'items' => $branchId ? $stock->forBranch($branchId) : collect(),
            'reasons' => WastageReason::options(),
            'branches' => $user->isAdminSide()
                ? Branch::active()->orderBy('name')->get(['id', 'name'])
                : [],
            'filters' => [
                'branch' => $branchId,
                'search' => $search,
                'reason' => $reason ?: null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'qty' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ], [
            'qty.required' => 'Enter how much was thrown away.',
            'qty.min' => 'Enter how much was thrown away.',
            'reason.required' => 'Pick what happened to it.',
        ]);

        $user = $request->user();

        $chosenBranchId = $validated['branch_id'] ?? null;

        $branch = ($user->isAdminSide() && $chosenBranchId)
            ? Branch::findOrFail($chosenBranchId)
            : $user->branch;

        abort_unless($branch !== null, 403);

        $item = Item::findOrFail($validated['item_id']);
        $reason = WastageReason::from($validated['reason']);

        try {
            $wastage = $this->operations->recordWastage(
                $branch,
                Quantity::fromOrderUnit($validated['qty'], $item),
                $reason,
                $user,
                $validated['note'] ?? null,
            );
        } catch (StockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($request->hasFile('photo')) {
            $wastage->addMediaFromRequest('photo')->toMediaCollection('photo');
        }

        return back()->with('success', "{$item->name} taken off your stock.");
    }
}
