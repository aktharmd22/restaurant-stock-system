<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\History\HistoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Why is that number what it is?"
 *
 * The ledger has always held the answer. This is where a person can read it.
 */
class HistoryController extends Controller
{
    public function __construct(private readonly HistoryService $history)
    {
    }

    /** Every stock movement, newest first. */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('Admin/History/Index', [
            'movements' => $this->history->movements($filters),
            'filters' => $filters,
            'branches' => $this->branchOptions(),
            'people' => $this->peopleOptions(),
            'types' => $this->typeOptions(),
            'currency' => setting('currency_symbol'),
        ]);
    }

    /** Who changed which record, and what it was before. */
    public function changes(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('Admin/History/Changes', [
            'changes' => $this->history->changes($filters),
            'filters' => $filters,
            'people' => $this->peopleOptions(),
            'subjects' => [
                ['value' => '', 'label' => 'Everything'],
                ['value' => 'Item', 'label' => 'Items'],
                ['value' => 'BranchItemSetting', 'label' => 'Par levels'],
                ['value' => 'Branch', 'label' => 'Branches'],
                ['value' => 'User', 'label' => 'People'],
                ['value' => 'Supplier', 'label' => 'Suppliers'],
                ['value' => 'Category', 'label' => 'Item groups'],
                ['value' => 'StockRequest', 'label' => 'Requests'],
            ],
        ]);
    }

    /**
     * One item at one branch, from its first movement to now, with the running
     * balance beside every line.
     */
    public function item(Request $request, Item $item): Response
    {
        $branch = ($request->integer('branch') ? Branch::find($request->integer('branch')) : null)
            ?? Branch::main()
            ?? Branch::active()->firstOrFail();

        $balance = StockBalance::withoutBranchScope()
            ->where('branch_id', $branch->id)
            ->where('item_id', $item->id)
            ->first();

        return Inertia::render('Admin/History/Item', [
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->quantity(0)->unitLabel(),
                'on_hand' => $item->quantity($balance?->qty_on_hand ?? 0)->forDisplay(),
                'reserved' => $item->quantity($balance?->qty_reserved ?? 0)->forDisplay(),
                'available' => $item->quantity($balance ? $balance->availableBase() : 0)->forDisplay(),
                'avg_cost' => (float) ($balance?->avg_cost ?? 0),
            ],
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'branches' => $this->branchOptions(),
            'movements' => $this->history->forItem($item->id, $branch->id),
            'currency' => setting('currency_symbol'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'branch' => $request->integer('branch') ?: null,
            'item' => $request->integer('item') ?: null,
            'type' => $request->string('type')->value() ?: null,
            'who' => $request->integer('who') ?: null,
            'subject' => $request->string('subject')->value() ?: null,
            'from' => $request->string('from')->value() ?: null,
            'to' => $request->string('to')->value() ?: null,
            'search' => $request->string('search')->trim()->value() ?: null,
        ];
    }

    /** @return array<int, array{value: int, label: string}> */
    private function branchOptions(): array
    {
        return Branch::orderByRaw("FIELD(type, 'main', 'sub')")
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch) => ['value' => $branch->id, 'label' => $branch->name])
            ->all();
    }

    /** @return array<int, array{value: int, label: string}> */
    private function peopleOptions(): array
    {
        return User::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['value' => $user->id, 'label' => $user->name])
            ->all();
    }

    /** @return array<int, array{value: string, label: string}> */
    private function typeOptions(): array
    {
        return array_map(fn (MovementType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ], MovementType::cases());
    }
}
