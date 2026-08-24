<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Models\Item;
use App\Models\LocalPurchase;
use App\Services\Stock\StockOperationsService;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Emergency buying. A branch that runs out mid-service will buy locally
 * whatever the app says - so the app may as well record it, with a photo of
 * the bill, and let the admin decide whether it counts.
 */
class LocalPurchaseController extends Controller
{
    public function __construct(private readonly StockOperationsService $operations)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $purchases = LocalPurchase::with(['item', 'requestedBy', 'branch'])
            ->latest()
            ->paginate(20)
            ->through(fn (LocalPurchase $purchase) => [
                'id' => $purchase->id,
                'item' => $purchase->item->name,
                'qty_text' => $purchase->quantity()->forDisplay(),
                'amount' => (float) $purchase->amount,
                'reason' => $purchase->reason,
                'status' => $purchase->status,
                'branch' => $purchase->branch?->name,
                'by' => $purchase->requestedBy?->firstName(),
                'when' => $purchase->created_at->format('D j M, g:i a'),
                'bill' => $purchase->billUrl(),
                'decision_note' => $purchase->decision_note,
            ]);

        return Inertia::render('LocalPurchase/Index', [
            'purchases' => $purchases,
            'items' => Item::active()->ordered()->get(['id', 'name', 'order_unit', 'conversion_factor', 'step_x100']),
            'canDecide' => $user->can('local_purchase.approve'),
            'canRequest' => $user->can('local_purchase.request'),
            'currency' => setting('currency_symbol'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('local_purchase.request'), 403);
        abort_unless($request->user()->branch_id !== null, 403);

        $validated = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'qty' => ['required', 'numeric', 'min:0.001'],
            'amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
            'bill' => ['nullable', 'image', 'max:4096'],
        ], [
            'qty.required' => 'Enter how much you bought.',
            'amount.required' => 'Enter what you paid.',
            'reason.required' => 'Say why you had to buy it.',
        ]);

        $item = Item::findOrFail($validated['item_id']);

        $purchase = LocalPurchase::create([
            'branch_id' => $request->user()->branch_id,
            'item_id' => $item->id,
            'qty' => Quantity::fromOrderUnit($validated['qty'], $item)->baseUnits,
            'amount' => $validated['amount'],
            'reason' => $validated['reason'],
            'status' => 'waiting',
            'requested_by' => $request->user()->id,
        ]);

        if ($request->hasFile('bill')) {
            $purchase->addMediaFromRequest('bill')->toMediaCollection('bill');
        }

        return back()->with('success', 'Sent to the main store to approve.');
    }

    public function approve(Request $request, LocalPurchase $localPurchase): RedirectResponse
    {
        abort_unless($request->user()->can('local_purchase.approve'), 403);

        try {
            $this->operations->approveLocalPurchase($localPurchase, $request->user(), $request->input('note'));
        } catch (StockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Approved and added to that branch\'s stock.');
    }

    public function reject(Request $request, LocalPurchase $localPurchase): RedirectResponse
    {
        abort_unless($request->user()->can('local_purchase.approve'), 403);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:255'],
        ], [
            'note.required' => 'Say why, so the branch knows what to do next time.',
        ]);

        try {
            $this->operations->rejectLocalPurchase($localPurchase, $request->user(), $validated['note']);
        } catch (StockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Not approved. The branch has been told why.');
    }
}
