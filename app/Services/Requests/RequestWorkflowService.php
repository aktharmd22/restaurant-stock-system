<?php

namespace App\Services\Requests;

use App\Enums\DiscrepancyReason;
use App\Enums\LineStatus;
use App\Enums\ReasonCode;
use App\Enums\RequestStatus;
use App\Exceptions\StockException;
use App\Models\Branch;
use App\Models\DispatchNote;
use App\Models\Item;
use App\Models\ReceiptDiscrepancy;
use App\Models\RequestLine;
use App\Enums\MovementType;
use App\Models\StockLedger;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\AlertService;
use App\Services\CutoffService;
use App\Services\SequenceService;
use App\Services\Stock\ReservationService;
use App\Services\Stock\StockLedgerService;
use App\Support\Quantity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ask -> approve -> send -> receive.
 *
 * Owns the state machine and the four quantities. Every step is a database
 * transaction, and every step that touches stock takes its row locks in
 * ascending item id order so two multi-line approvals cannot deadlock.
 */
class RequestWorkflowService
{
    public function __construct(
        private readonly StockLedgerService $ledger,
        private readonly ReservationService $reservations,
        private readonly SequenceService $sequences,
        private readonly CutoffService $cutoff,
        private readonly AlertService $alerts,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | 1. The branch asks
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<int, array{item_id: int, qty: float|int|string}>  $lines
     *                                                                        Quantities arrive in ORDER units - what the person tapped.
     */
    public function submit(
        Branch $from,
        User $by,
        array $lines,
        ?string $note = null,
        ?string $neededBy = null,
    ): StockRequest {
        $main = Branch::main();

        if (! $main) {
            throw new StockException('There is no main store set up yet. Ask your admin.');
        }

        if ($from->id === $main->id) {
            throw new StockException('The main store buys stock, it does not ask itself for it.');
        }

        $items = $this->itemsFor($lines);
        $quantities = $this->quantitiesFrom($lines, $items);

        if ($quantities->isEmpty()) {
            throw new StockException('Add at least one item before sending.');
        }

        $request = DB::transaction(function () use ($from, $main, $by, $quantities, $note, $neededBy) {
            $isLate = $this->cutoff->isLate($from);

            $request = StockRequest::create([
                'request_number' => $this->sequences->requestNumber($from->code),
                'from_branch_id' => $from->id,
                'to_branch_id' => $main->id,
                'status' => RequestStatus::Waiting,
                'needed_by' => $neededBy,
                'note' => $note,
                'is_late' => $isLate,
                // Snapshotted so editing the cut-off later cannot rewrite history.
                'cutoff_at' => $this->cutoff->todaysCutoff($from),
                'created_by' => $by->id,
                'submitted_at' => now(),
            ]);

            foreach ($quantities as $quantity) {
                RequestLine::create([
                    'request_id' => $request->id,
                    'item_id' => $quantity->item->id,
                    'qty_requested' => $quantity->baseUnits,
                    'line_status' => LineStatus::Waiting,
                ]);
            }

            return $request->fresh(['lines']);
        });

        // Alerts fire only after the transaction commits. Telling the main
        // store about a request that then rolls back would be worse than
        // telling them nothing.
        $this->alerts->requestSubmitted($request->load('fromBranch'));

        return $request;
    }

    /*
    |--------------------------------------------------------------------------
    | 2. The admin decides
    |--------------------------------------------------------------------------
    */

    /**
     * Approve line by line. One save covers "all of line 1, half of line 2,
     * none of line 3".
     *
     * @param  array<int, array{qty: float|int|string, reason_code?: ?string, note?: ?string}>  $decisions
     *                                                                                                     Keyed by request line id. Quantities in ORDER units.
     */
    public function approve(StockRequest $request, User $admin, array $decisions): StockRequest
    {
        if ($request->status !== RequestStatus::Waiting) {
            throw new StockException('This request has already been looked at.');
        }

        $reviewed = DB::transaction(function () use ($request, $admin, $decisions) {
            // Ascending item id: the lock order that prevents deadlocks.
            $lines = $request->lines()->with('item')->orderBy('item_id')->get();

            foreach ($lines as $line) {
                $decision = $decisions[$line->id] ?? ['qty' => $line->qty_requested / $line->item->conversion_factor];

                $approved = Quantity::fromOrderUnit($decision['qty'] ?? 0, $line->item);
                $requested = $line->requested();

                if ($approved->baseUnits < 0) {
                    throw new StockException("A quantity cannot be less than nothing ({$line->item->name}).");
                }

                if ($approved->greaterThan($requested)) {
                    throw new StockException(
                        "You cannot approve more {$line->item->name} than was asked for "
                        ."({$requested->forDisplay()})."
                    );
                }

                $reasonCode = isset($decision['reason_code'])
                    ? ReasonCode::tryFrom((string) $decision['reason_code'])
                    : null;

                $status = match (true) {
                    $approved->isZero() => LineStatus::Rejected,
                    $approved->baseUnits < $requested->baseUnits => LineStatus::Reduced,
                    default => LineStatus::Approved,
                };

                // Cutting someone's order without saying why is how trust in
                // this system dies.
                if ($status !== LineStatus::Approved && ! $reasonCode) {
                    throw new StockException("Choose a reason for {$line->item->name}.");
                }

                if ($approved->isPositive()) {
                    $this->reservations->reserve($request->to_branch_id, $approved);
                }

                $line->update([
                    'qty_approved' => $approved->baseUnits,
                    'line_status' => $status,
                    'reason_code' => $status === LineStatus::Approved ? null : $reasonCode,
                    'admin_note' => $decision['note'] ?? null,
                ]);
            }

            $request->update([
                'status' => $this->deriveStatus($request->lines()->get()),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            return $request->fresh(['lines.item']);
        });

        $this->alerts->requestReviewed($reviewed->load('fromBranch'));

        return $reviewed;
    }

    /**
     * The common case: everything as asked, one click.
     *
     * If stock is short anywhere it refuses and names the items, rather than
     * quietly approving less than the branch sees on screen.
     */
    public function approveAll(StockRequest $request, User $admin): StockRequest
    {
        $short = $this->shortages($request);

        if ($short->isNotEmpty()) {
            throw new StockException(
                'Not enough stock for '.$short->join(', ', ' and ').'. Reduce those lines, then save.',
            );
        }

        $decisions = $request->lines()->with('item')->get()
            ->mapWithKeys(fn (RequestLine $line) => [
                $line->id => ['qty' => $line->requested()->toOrderUnit()],
            ])
            ->all();

        return $this->approve($request, $admin, $decisions);
    }

    /**
     * Items on this request the main store cannot fully cover right now.
     *
     * @return Collection<int, string>
     */
    public function shortages(StockRequest $request): Collection
    {
        return $request->lines()->with('item')->get()
            ->filter(function (RequestLine $line) use ($request) {
                $available = $this->reservations->availableBase($request->to_branch_id, $line->item_id);

                return $available < $line->qty_requested;
            })
            ->map(fn (RequestLine $line) => $line->item->name)
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | 3. The store keeper sends it
    |--------------------------------------------------------------------------
    */

    /**
     * Stock leaves the main store here, and only here.
     *
     * @param  array<int, float|int|string>  $sentQuantities  Keyed by line id, in ORDER units.
     */
    public function dispatch(
        StockRequest $request,
        User $by,
        array $sentQuantities = [],
        ?string $carrier = null,
        ?string $vehicle = null,
    ): StockRequest {
        if (! $request->status->awaitingDispatch()) {
            throw new StockException('This request is not ready to send.');
        }

        $sentRequest = DB::transaction(function () use ($request, $by, $sentQuantities, $carrier, $vehicle) {
            $lines = $request->lines()->with('item')->orderBy('item_id')->get();
            $anythingSent = false;

            foreach ($lines as $line) {
                $approved = $line->approved();

                if ($approved->isZero()) {
                    continue;
                }

                $sent = array_key_exists($line->id, $sentQuantities)
                    ? Quantity::fromOrderUnit($sentQuantities[$line->id], $line->item)
                    : $approved;

                if ($sent->greaterThan($approved)) {
                    throw new StockException(
                        "You cannot send more {$line->item->name} than was approved "
                        ."({$approved->forDisplay()})."
                    );
                }

                // The promise is finished with either way: what actually goes
                // out is now a real movement.
                $this->reservations->release($request->to_branch_id, $approved);

                if ($sent->isPositive()) {
                    // Value it at what the main store paid, and remember that on
                    // the line so the branch receives it at the same price.
                    $unitCost = $this->ledger->averageCost($request->to_branch_id, $line->item_id);

                    $this->ledger->transferOut($request->to_branch_id, $sent, $line, $by, $unitCost ?: null);
                    $anythingSent = true;
                }

                $line->update([
                    'qty_sent' => $sent->baseUnits,
                    'line_status' => LineStatus::Sent,
                ]);
            }

            if (! $anythingSent) {
                throw new StockException('There is nothing to send on this request.');
            }

            DispatchNote::create([
                'request_id' => $request->id,
                'note_number' => $this->sequences->dispatchNoteNumber(),
                'sent_by' => $by->id,
                'carrier_name' => $carrier,
                'vehicle_number' => $vehicle,
                'sent_at' => now(),
            ]);

            $request->update([
                'status' => RequestStatus::Sent,
                'dispatched_by' => $by->id,
                'dispatched_at' => now(),
            ]);

            return $request->fresh(['lines.item', 'dispatchNote']);
        });

        $this->alerts->requestDispatched($sentRequest->load('fromBranch'));

        return $sentRequest;
    }

    /*
    |--------------------------------------------------------------------------
    | 4. The branch confirms what turned up
    |--------------------------------------------------------------------------
    */

    /**
     * Stock arrives at the branch here, and only here. Between dispatch and
     * this moment it belongs to neither branch - that is goods in transit.
     *
     * @param  array<int, array{qty: float|int|string, reason?: ?string, note?: ?string}>  $received
     *                                                                                               Keyed by line id, quantities in ORDER units.
     */
    public function receive(StockRequest $request, User $by, array $received = []): StockRequest
    {
        if (! $request->status->inTransit()) {
            throw new StockException('This delivery has already been confirmed.');
        }

        $receivedRequest = DB::transaction(function () use ($request, $by, $received) {
            $lines = $request->lines()->with('item')->orderBy('item_id')->get();

            foreach ($lines as $line) {
                $sent = $line->sent();

                if ($sent->isZero()) {
                    continue;
                }

                $confirmed = array_key_exists($line->id, $received)
                    ? Quantity::fromOrderUnit($received[$line->id]['qty'] ?? 0, $line->item)
                    : $sent;

                if ($confirmed->greaterThan($sent)) {
                    throw new StockException(
                        "More {$line->item->name} cannot arrive than was sent ({$sent->forDisplay()})."
                    );
                }

                if ($confirmed->isPositive()) {
                    // Same price it left the main store at.
                    $sentCost = StockLedger::withoutBranchScope()
                        ->where('reference_type', 'RequestLine')
                        ->where('reference_id', $line->id)
                        ->where('movement_type', MovementType::TransferOut)
                        ->value('unit_cost');

                    $this->ledger->transferIn(
                        $request->from_branch_id,
                        $confirmed,
                        $line,
                        $sentCost !== null ? (float) $sentCost : null,
                        $by,
                    );
                }

                $line->update([
                    'qty_received' => $confirmed->baseUnits,
                    'line_status' => LineStatus::Received,
                ]);

                // Short delivery without a record is how a branch gets blamed
                // for something that happened on the van.
                if ($confirmed->baseUnits < $sent->baseUnits) {
                    $reason = DiscrepancyReason::tryFrom((string) ($received[$line->id]['reason'] ?? ''));

                    if (! $reason) {
                        throw new StockException("Say what happened to the missing {$line->item->name}.");
                    }

                    ReceiptDiscrepancy::create([
                        'request_line_id' => $line->id,
                        'qty_short' => $sent->baseUnits - $confirmed->baseUnits,
                        'reason' => $reason,
                        'note' => $received[$line->id]['note'] ?? null,
                        'reported_by' => $by->id,
                    ]);
                }
            }

            $request->update([
                'status' => RequestStatus::Received,
                'received_by' => $by->id,
                'received_at' => now(),
            ]);

            return $request->fresh(['lines.item']);
        });

        $this->alerts->requestReceived($receivedRequest->load('fromBranch'));

        return $receivedRequest;
    }

    /*
    |--------------------------------------------------------------------------
    | Cancelling
    |--------------------------------------------------------------------------
    */

    public function cancel(StockRequest $request, User $by, ?string $reason = null): StockRequest
    {
        if (! $request->status->canBeCancelled()) {
            throw new StockException('This request has already gone out and cannot be cancelled.');
        }

        $wasSubmitted = $request->submitted_at !== null;

        $cancelled = DB::transaction(function () use ($request, $by, $reason) {
            // Anything promised goes back into the pool for other branches.
            if ($request->status->awaitingDispatch()) {
                foreach ($request->lines()->with('item')->orderBy('item_id')->get() as $line) {
                    if ($line->approved()->isPositive()) {
                        $this->reservations->release($request->to_branch_id, $line->approved());
                    }
                }
            }

            $request->update([
                'status' => RequestStatus::Cancelled,
                'cancelled_by' => $by->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $request->fresh();
        });

        if ($wasSubmitted) {
            $this->alerts->requestCancelled($cancelled->load('fromBranch'));
        }

        return $cancelled;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * The request's status is never set by hand - it is read off its lines.
     *
     * @param  Collection<int, RequestLine>  $lines
     */
    private function deriveStatus(Collection $lines): RequestStatus
    {
        $total = $lines->count();
        $rejected = $lines->where('line_status', LineStatus::Rejected)->count();
        $approved = $lines->where('line_status', LineStatus::Approved)->count();

        return match (true) {
            $rejected === $total => RequestStatus::Rejected,
            $approved === $total => RequestStatus::Approved,
            default => RequestStatus::Partial,
        };
    }

    /**
     * @param  array<int, array{item_id: int, qty: float|int|string}>  $lines
     * @return Collection<int, Item>
     */
    private function itemsFor(array $lines): Collection
    {
        $ids = collect($lines)->pluck('item_id')->filter()->unique();

        return Item::active()->whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * Turn what a person tapped into whole base units, dropping empty lines
     * and merging any duplicates.
     *
     * @param  array<int, array{item_id: int, qty: float|int|string}>  $lines
     * @param  Collection<int, Item>  $items
     * @return Collection<int, Quantity>
     */
    private function quantitiesFrom(array $lines, Collection $items): Collection
    {
        $merged = [];

        foreach ($lines as $line) {
            $item = $items->get((int) ($line['item_id'] ?? 0));

            if (! $item) {
                continue;
            }

            $quantity = Quantity::fromOrderUnit($line['qty'] ?? 0, $item);

            if (! $quantity->isPositive()) {
                continue;
            }

            $merged[$item->id] = isset($merged[$item->id])
                ? $merged[$item->id]->plus($quantity)
                : $quantity;
        }

        return collect($merged)->values();
    }
}
