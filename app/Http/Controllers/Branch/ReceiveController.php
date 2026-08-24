<?php

namespace App\Http\Controllers\Branch;

use App\Enums\DiscrepancyReason;
use App\Enums\RequestStatus;
use App\Exceptions\StockException;
use App\Http\Controllers\Controller;
use App\Http\Resources\RequestPresenter;
use App\Models\ReceiptDiscrepancy;
use App\Models\StockRequest;
use App\Services\Requests\RequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Confirming what actually turned up. The default is "everything arrived",
 * because that is what usually happens - the common case is one tap.
 */
class ReceiveController extends Controller
{
    public function __construct(private readonly RequestWorkflowService $workflow)
    {
    }

    public function index(Request $request): Response
    {
        $onTheWay = StockRequest::with(['lines', 'dispatchNote'])
            ->withCount('lines')
            ->where('from_branch_id', $request->user()->branch_id)
            ->where('status', RequestStatus::Sent)
            ->orderBy('dispatched_at')
            ->get();

        return Inertia::render('Branch/Receive', [
            'deliveries' => $onTheWay->map(fn (StockRequest $item) => [
                ...RequestPresenter::summary($item),
                'carrier' => $item->dispatchNote?->carrier_name,
                'vehicle' => $item->dispatchNote?->vehicle_number,
                'note_number' => $item->dispatchNote?->note_number,
            ]),
        ]);
    }

    public function show(StockRequest $stockRequest): Response
    {
        $this->authorize('receive', $stockRequest);

        $stockRequest->load(['lines.item', 'dispatchNote']);

        return Inertia::render('Branch/ConfirmDelivery', [
            'request' => RequestPresenter::detail($stockRequest),
            'reasons' => DiscrepancyReason::options(),
        ]);
    }

    public function store(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $this->authorize('receive', $stockRequest);

        $validated = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.qty' => ['required', 'numeric', 'min:0'],
            'lines.*.reason' => ['nullable', 'string'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
            'photos' => ['array'],
            'photos.*' => ['nullable', 'image', 'max:4096'],
        ]);

        try {
            $stockRequest = $this->workflow->receive(
                $stockRequest,
                $request->user(),
                $validated['lines'],
            );
        } catch (StockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->attachPhotos($request, $stockRequest);

        return redirect()
            ->route('branch.requests.show', $stockRequest->id)
            ->with('success', 'Thanks. Stock added to your branch.');
    }

    /**
     * A photo of a damaged box settles an argument that words cannot.
     */
    private function attachPhotos(Request $request, StockRequest $stockRequest): void
    {
        foreach ($request->file('photos', []) as $lineId => $photo) {
            if (! $photo) {
                continue;
            }

            $discrepancy = ReceiptDiscrepancy::where('request_line_id', $lineId)->latest('id')->first();

            $discrepancy?->addMedia($photo)->toMediaCollection('photo');
        }
    }
}
