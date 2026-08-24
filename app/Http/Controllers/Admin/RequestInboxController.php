<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReasonCode;
use App\Enums\RequestStatus;
use App\Exceptions\StockException;
use App\Http\Controllers\Controller;
use App\Http\Resources\RequestPresenter;
use App\Models\Branch;
use App\Models\StockBalance;
use App\Models\StockRequest;
use App\Services\Requests\RequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The most important screen in the app.
 *
 * The one thing it must never do is make the admin look up stock somewhere
 * else: every line shows what is actually free at the main store, right next
 * to what was asked for.
 */
class RequestInboxController extends Controller
{
    public function __construct(private readonly RequestWorkflowService $workflow)
    {
    }

    public function index(Request $request): Response
    {
        $status = $request->string('status')->value() ?: 'waiting';

        $requests = StockRequest::with('fromBranch')
            ->withCount('lines')
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($request->integer('branch'), fn ($query, $id) => $query->where('from_branch_id', $id))
            ->when($request->date('from'), fn ($query, $date) => $query->whereDate('submitted_at', '>=', $date))
            ->when($request->date('to'), fn ($query, $date) => $query->whereDate('submitted_at', '<=', $date))
            ->mostUrgentFirst()
            ->paginate(20)
            ->withQueryString();

        $selected = $this->selectedRequest($request, $requests->getCollection());

        return Inertia::render('Admin/Requests/Inbox', [
            'requests' => $requests->through(fn (StockRequest $item) => RequestPresenter::summary($item)),
            'selected' => $selected,
            'branches' => Branch::sub()->active()->orderBy('name')->get(['id', 'name']),
            'reasons' => ReasonCode::options(),
            'filters' => [
                'status' => $status,
                'branch' => $request->integer('branch') ?: null,
                'from' => $request->string('from')->value(),
                'to' => $request->string('to')->value(),
            ],
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function approve(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $this->authorize('approve', $stockRequest);

        $validated = $request->validate([
            'decisions' => ['required', 'array'],
            'decisions.*.qty' => ['required', 'numeric', 'min:0'],
            'decisions.*.reason_code' => ['nullable', 'string'],
            'decisions.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->workflow->approve($stockRequest, $request->user(), $validated['decisions']);
        } catch (StockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Saved. The branch has been told.');
    }

    public function approveAll(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $this->authorize('approve', $stockRequest);

        try {
            $this->workflow->approveAll($stockRequest, $request->user());
        } catch (StockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Everything approved. The branch has been told.');
    }

    public function cancel(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $this->authorize('cancel', $stockRequest);

        try {
            $this->workflow->cancel($stockRequest, $request->user(), $request->input('reason'));
        } catch (StockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Request cancelled.');
    }

    /**
     * The request shown on the right, with free stock beside every line.
     *
     * @param  \Illuminate\Support\Collection<int, StockRequest>  $visible
     * @return array<string, mixed>|null
     */
    private function selectedRequest(Request $request, $visible): ?array
    {
        $id = $request->integer('selected') ?: $visible->first()?->id;

        if (! $id) {
            return null;
        }

        $stockRequest = StockRequest::with(['lines.item', 'fromBranch', 'dispatchNote'])->find($id);

        if (! $stockRequest) {
            return null;
        }

        $available = StockBalance::withoutBranchScope()
            ->where('branch_id', $stockRequest->to_branch_id)
            ->whereIn('item_id', $stockRequest->lines->pluck('item_id'))
            ->get()
            ->keyBy('item_id');

        $detail = RequestPresenter::detail($stockRequest);

        $detail['lines'] = $stockRequest->lines->map(function ($line) use ($available) {
            $balance = $available->get($line->item_id);
            $freeBase = $balance ? $balance->availableBase() : 0;
            $free = $line->item->quantity(max(0, $freeBase));

            return [
                ...RequestPresenter::line($line),
                'available' => $free->toOrderUnit(),
                'available_text' => $free->forDisplay(),
                // Highlighted amber on screen, so a short line cannot be missed.
                'is_short' => $freeBase < $line->qty_requested,
            ];
        })->values();

        $detail['can_approve'] = $request->user()->can('approve', $stockRequest);
        $detail['can_cancel'] = $request->user()->can('cancel', $stockRequest);

        return $detail;
    }

    /** @return array<int, array{value: string, label: string}> */
    private function statusOptions(): array
    {
        $statuses = [
            RequestStatus::Waiting,
            RequestStatus::Approved,
            RequestStatus::Partial,
            RequestStatus::Sent,
            RequestStatus::Received,
            RequestStatus::Rejected,
            RequestStatus::Cancelled,
        ];

        return [
            ['value' => 'all', 'label' => 'Everything'],
            ...array_map(fn (RequestStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ], $statuses),
        ];
    }
}
