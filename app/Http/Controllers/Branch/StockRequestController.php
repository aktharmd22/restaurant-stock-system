<?php

namespace App\Http\Controllers\Branch;

use App\Exceptions\StockException;
use App\Http\Controllers\Controller;
use App\Http\Resources\RequestPresenter;
use App\Models\Category;
use App\Models\StockRequest;
use App\Services\CutoffService;
use App\Services\Requests\RequestWorkflowService;
use App\Services\Stock\LowStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockRequestController extends Controller
{
    public function __construct(private readonly RequestWorkflowService $workflow)
    {
    }

    /** My requests, newest first. */
    public function index(Request $request): Response
    {
        $requests = StockRequest::with('lines')
            ->withCount('lines')
            ->where('from_branch_id', $request->user()->branch_id)
            ->latest('submitted_at')
            ->paginate(15);

        return Inertia::render('Branch/MyRequests', [
            'requests' => $requests->through(fn (StockRequest $item) => RequestPresenter::summary($item)),
        ]);
    }

    public function show(Request $request, StockRequest $stockRequest): Response
    {
        $this->authorize('view', $stockRequest);

        $stockRequest->load(['lines.item', 'dispatchNote']);

        // Looking at it counts as reading the alert about it.
        $request->user()->unreadNotifications()
            ->where('data->request_id', $stockRequest->id)
            ->update(['read_at' => now()]);

        return Inertia::render('Branch/RequestDetail', [
            'request' => RequestPresenter::detail($stockRequest),
            'canCancel' => $request->user()->can('cancel', $stockRequest),
            // Set straight after sending, so the screen can confirm and offer Undo.
            'justSent' => $request->boolean('sent'),
        ]);
    }

    /** The Ask for stock screen. */
    public function create(Request $request, LowStockService $lowStock, CutoffService $cutoff): Response
    {
        $user = $request->user();
        $branch = $user->branch;

        $lastRequest = StockRequest::with('lines.item')
            ->where('from_branch_id', $branch->id)
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        return Inertia::render('Branch/AskForStock', [
            'items' => $lowStock->itemsForBranch($branch->id),
            'categories' => Category::active()->ordered()->get(['id', 'name']),
            'cutoff' => $cutoff->countdown($branch),

            // "Same as last time" fills the whole form in one tap - most days a
            // kitchen orders close to the same thing.
            'lastTime' => $lastRequest?->lines->map(fn ($line) => [
                'item_id' => $line->item_id,
                'qty' => $line->requested()->toOrderUnit(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', StockRequest::class);

        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'lines.required' => 'Add at least one item before sending.',
            'needed_by.after_or_equal' => 'Pick today or a day after it.',
        ]);

        try {
            $stockRequest = $this->workflow->submit(
                $request->user()->branch,
                $request->user(),
                $validated['lines'],
                $validated['note'] ?? null,
                $validated['needed_by'] ?? null,
            );
        } catch (StockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('branch.requests.show', ['stockRequest' => $stockRequest->id, 'sent' => 1])
            ->with('success', 'Request sent.');
    }

    /**
     * Used by the 10-second Undo after sending, and by the detail screen while
     * a request is still waiting.
     */
    public function cancel(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $this->authorize('cancel', $stockRequest);

        try {
            $this->workflow->cancel($stockRequest, $request->user(), $request->input('reason'));
        } catch (StockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('branch.requests.index')
            ->with('success', 'Request cancelled.');
    }
}
