<?php

namespace App\Http\Resources;

use App\Models\RequestLine;
use App\Models\StockRequest;

/**
 * One place that turns a request into the shape the screens expect, so the
 * branch view and the admin view can never drift apart on wording or numbers.
 */
class RequestPresenter
{
    /**
     * The row card in a list: date, how many items, where it has got to.
     *
     * @return array<string, mixed>
     */
    public static function summary(StockRequest $request): array
    {
        return [
            'id' => $request->id,
            'number' => $request->request_number,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'is_late' => $request->is_late,
            'branch' => $request->relationLoaded('fromBranch') ? $request->fromBranch?->name : null,
            'item_count' => $request->lines_count ?? $request->lines->count(),
            'sent_at' => $request->submitted_at?->toIso8601String(),
            'sent_at_text' => $request->submitted_at?->timezone(config('app.timezone'))->format('D j M, g:i a'),
            'needed_by' => $request->needed_by?->format('D j M'),
            'note' => $request->note,
        ];
    }

    /**
     * The full picture: every line with all four numbers and, where a line was
     * cut, the reason in plain words.
     *
     * @return array<string, mixed>
     */
    public static function detail(StockRequest $request): array
    {
        return [
            ...self::summary($request),
            'reviewed_at_text' => $request->reviewed_at?->timezone(config('app.timezone'))->format('D j M, g:i a'),
            'dispatched_at_text' => $request->dispatched_at?->timezone(config('app.timezone'))->format('D j M, g:i a'),
            'received_at_text' => $request->received_at?->timezone(config('app.timezone'))->format('D j M, g:i a'),
            'cancel_reason' => $request->cancel_reason,
            'carrier' => $request->relationLoaded('dispatchNote') ? $request->dispatchNote?->carrier_name : null,
            'vehicle' => $request->relationLoaded('dispatchNote') ? $request->dispatchNote?->vehicle_number : null,
            'note_number' => $request->relationLoaded('dispatchNote') ? $request->dispatchNote?->note_number : null,
            'lines' => $request->lines->map(fn (RequestLine $line) => self::line($line))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function line(RequestLine $line): array
    {
        return [
            'id' => $line->id,
            'item_id' => $line->item_id,
            'item' => $line->item->name,
            'photo' => $line->item->photoUrl(),
            'unit' => $line->requested()->unitLabel(),
            'step' => $line->item->stepSize(),
            'decimals' => $line->item->decimals(),

            // The four numbers. The gaps between them are the whole point.
            'requested' => $line->requested()->toOrderUnit(),
            'requested_text' => $line->requested()->forDisplay(),
            'approved' => $line->qty_approved === null ? null : $line->approved()->toOrderUnit(),
            'approved_text' => $line->qty_approved === null ? null : $line->approved()->forDisplay(),
            'sent' => $line->qty_sent === null ? null : $line->sent()->toOrderUnit(),
            'sent_text' => $line->qty_sent === null ? null : $line->sent()->forDisplay(),
            'received' => $line->qty_received === null ? null : $line->received()->toOrderUnit(),
            'received_text' => $line->qty_received === null ? null : $line->received()->forDisplay(),

            'status' => $line->line_status->value,
            'status_label' => $line->line_status->label(),
            'tone' => $line->line_status->tone(),
            'reason' => $line->reasonText(),
            'was_cut' => $line->wasCut(),
        ];
    }
}
