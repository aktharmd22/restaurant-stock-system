<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\StockException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Purchasing\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases)
    {
    }

    public function index(Request $request): Response
    {
        $status = $request->string('status')->value() ?: 'open';

        $search = $request->string('search')->trim()->value();

        $orders = PurchaseOrder::with(['supplier', 'branch'])
            ->withCount('lines')
            ->when($status === 'open', fn ($query) => $query->whereIn('status', ['ordered', 'part_received']))
            ->when($status === 'done', fn ($query) => $query->whereIn('status', ['received', 'cancelled']))
            ->when($search, fn ($query, string $term) => $query->where(function ($inner) use ($term) {
                $inner->where('po_number', 'like', "%{$term}%")
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$term}%"));
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'number' => $order->po_number,
                'supplier' => $order->supplier->name,
                'branch' => $order->branch->name,
                'status' => $order->status,
                'status_label' => $this->statusLabel($order->status),
                'tone' => $this->statusTone($order->status),
                'lines' => $order->lines_count,
                'total' => (float) $order->total_amount,
                'expected' => $order->expected_date?->format('D j M'),
                'placed' => $order->created_at->format('D j M'),
            ]);

        return Inertia::render('Admin/Purchase/Index', [
            'orders' => $orders,
            'filters' => ['status' => $status, 'search' => $search],
            'currency' => setting('currency_symbol'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Purchase/Create', [
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::active()
                ->orderByRaw("FIELD(type, 'main', 'sub')")
                ->orderBy('name')
                ->get(['id', 'name']),
            'items' => Item::active()->with('category')->ordered()->get()->map(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category->name,
                'unit' => $item->quantity(0)->unitLabel(),
                'step' => $item->stepSize(),
                'decimals' => $item->decimals(),
            ]),
            'currency' => setting('currency_symbol'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'expected_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'exists:items,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ], [
            'supplier_id.required' => 'Pick who you are buying from.',
            'lines.required' => 'Add at least one item to the order.',
        ]);

        try {
            $order = $this->purchases->createOrder(
                $validated['supplier_id'],
                $validated['branch_id'],
                $request->user(),
                $validated['lines'],
                $validated['expected_date'] ?? null,
                $validated['note'] ?? null,
            );
        } catch (StockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.purchase.show', $order->id)
            ->with('success', "Order {$order->po_number} placed.");
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load(['lines.item', 'supplier', 'branch', 'createdBy']);

        return Inertia::render('Admin/Purchase/Show', [
            'order' => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->po_number,
                'supplier' => $purchaseOrder->supplier->name,
                'supplier_phone' => $purchaseOrder->supplier->phone,
                'branch' => $purchaseOrder->branch->name,
                'status' => $purchaseOrder->status,
                'status_label' => $this->statusLabel($purchaseOrder->status),
                'tone' => $this->statusTone($purchaseOrder->status),
                'total' => (float) $purchaseOrder->total_amount,
                'expected' => $purchaseOrder->expected_date?->format('D j M'),
                'placed' => $purchaseOrder->created_at->format('D j M, g:i a'),
                'note' => $purchaseOrder->note,
                'lines' => $purchaseOrder->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'item' => $line->item->name,
                    'unit' => $line->ordered()->unitLabel(),
                    'step' => $line->item->stepSize(),
                    'decimals' => $line->item->decimals(),
                    'ordered' => $line->ordered()->toOrderUnit(),
                    'ordered_text' => $line->ordered()->forDisplay(),
                    'received_text' => $line->item->quantity($line->qty_received)->forDisplay(),
                    'outstanding' => $line->item->quantity($line->outstandingBase())->toOrderUnit(),
                    'outstanding_text' => $line->item->quantity($line->outstandingBase())->forDisplay(),
                    // Shown per order unit, which is how the supplier quoted it.
                    'unit_price' => round((float) $line->unit_price * $line->item->conversion_factor, 2),
                    'line_total' => $line->lineTotal(),
                ]),
            ],
            'currency' => setting('currency_symbol'),
        ]);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*' => ['numeric', 'min:0'],
        ]);

        try {
            $this->purchases->receiveGoods($purchaseOrder, $request->user(), $validated['lines']);
        } catch (StockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Added to stock.');
    }

    /** What to buy next, totalled across every branch. */
    public function suggestions(): Response
    {
        $main = Branch::main();

        return Inertia::render('Admin/Purchase/Suggestions', [
            'rows' => $main ? $this->purchases->suggestions($main->id) : collect(),
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Not sent',
            'ordered' => 'Ordered',
            'part_received' => 'Some arrived',
            'received' => 'All arrived',
            'cancelled' => 'Cancelled',
            default => $status,
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'ordered' => 'waiting',
            'part_received' => 'partial',
            'received' => 'approved',
            'cancelled' => 'cancelled',
            default => 'draft',
        };
    }
}
