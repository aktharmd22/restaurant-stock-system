<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\StockException;
use App\Http\Controllers\Controller;
use App\Http\Resources\RequestPresenter;
use App\Models\StockRequest;
use App\Services\Requests\RequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DispatchController extends Controller
{
    public function __construct(private readonly RequestWorkflowService $workflow)
    {
    }

    public function index(): Response
    {
        $ready = StockRequest::with('fromBranch')
            ->withCount('lines')
            ->awaitingDispatch()
            ->mostUrgentFirst()
            ->get();

        return Inertia::render('Admin/Dispatch/Index', [
            'requests' => $ready->map(fn (StockRequest $request) => RequestPresenter::summary($request)),
        ]);
    }

    public function show(StockRequest $stockRequest): Response
    {
        $this->authorize('dispatch', $stockRequest);

        $stockRequest->load(['lines.item', 'fromBranch']);

        return Inertia::render('Admin/Dispatch/Pack', [
            'request' => RequestPresenter::detail($stockRequest),
            'packList' => $this->packList($stockRequest),
        ]);
    }

    public function store(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $this->authorize('dispatch', $stockRequest);

        $validated = $request->validate([
            'lines' => ['array'],
            'lines.*' => ['numeric', 'min:0'],
            'carrier_name' => ['nullable', 'string', 'max:60'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
        ]);

        try {
            $this->workflow->dispatch(
                $stockRequest,
                $request->user(),
                $validated['lines'] ?? [],
                $validated['carrier_name'] ?? null,
                $validated['vehicle_number'] ?? null,
            );
        } catch (StockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.dispatch.index')
            ->with('success', "{$stockRequest->fromBranch->name} has been told it is on the way.");
    }

    /**
     * Grouped by where things are kept, so the store keeper walks the store
     * once instead of five times. This is the whole reason
     * items.storage_location exists.
     *
     * @return array<int, array{location: string, lines: array<int, array<string, mixed>>}>
     */
    private function packList(StockRequest $stockRequest): array
    {
        return $stockRequest->lines
            ->filter(fn ($line) => $line->approved()->isPositive())
            ->sortBy(fn ($line) => $line->item->name)
            ->groupBy(fn ($line) => $line->item->storage_location ?: 'Anywhere else')
            ->sortKeys()
            ->map(fn ($lines, $location) => [
                'location' => $location,
                'lines' => $lines->map(fn ($line) => [
                    'id' => $line->id,
                    'item' => $line->item->name,
                    'approved' => $line->approved()->toOrderUnit(),
                    'approved_text' => $line->approved()->forDisplay(),
                    'unit' => $line->approved()->unitLabel(),
                    'step' => $line->item->stepSize(),
                    'decimals' => $line->item->decimals(),
                ])->values(),
            ])
            ->values()
            ->all();
    }
}
