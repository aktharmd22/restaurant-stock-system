<?php

namespace App\Services\History;

use App\Enums\MovementType;
use App\Models\Item;
use App\Models\StockLedger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reading the history back.
 *
 * The ledger already records every movement. What it does not do on its own is
 * explain itself: a row saying "-8000 g of Onion, reference RequestLine 412"
 * answers nothing. This turns each row into a sentence a person can act on -
 * what happened, how much, who did it, and what caused it, with a link to the
 * thing that caused it.
 */
class HistoryService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function movements(array $filters = [], int $perPage = 40): LengthAwarePaginator
    {
        $page = StockLedger::query()
            ->with(['item:id,name,base_unit,order_unit,conversion_factor,step_x100', 'createdBy:id,name'])
            ->join('branches', 'branches.id', '=', 'stock_ledger.branch_id')
            ->join('items', 'items.id', '=', 'stock_ledger.item_id')
            ->when($filters['branch'] ?? null, fn ($q, $id) => $q->where('stock_ledger.branch_id', $id))
            ->when($filters['item'] ?? null, fn ($q, $id) => $q->where('stock_ledger.item_id', $id))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('movement_type', $type))
            ->when($filters['who'] ?? null, fn ($q, $id) => $q->where('created_by', $id))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('stock_ledger.created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('stock_ledger.created_at', '<=', $date))
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where('items.name', 'like', "%{$term}%"))
            ->orderByDesc('stock_ledger.id')
            ->select('stock_ledger.*', 'branches.name as branch_name')
            ->paginate($perPage)
            ->withQueryString();

        $causes = $this->resolveCauses(collect($page->items()));

        return $page->through(fn (StockLedger $row) => $this->present($row, $causes));
    }

    /**
     * Everything that ever happened to one item at one branch, oldest first,
     * so the running balance reads as a story rather than a list.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forItem(int $itemId, int $branchId, int $limit = 200): Collection
    {
        $rows = StockLedger::query()
            ->with(['item', 'createdBy:id,name', 'branch:id,name'])
            ->where('item_id', $itemId)
            ->where('branch_id', $branchId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $causes = $this->resolveCauses($rows);

        return $rows->map(fn (StockLedger $row) => $this->present($row, $causes));
    }

    /**
     * Who changed what, and what it was before. Comes from the activity log
     * rather than the ledger - these are edits to records, not stock moving.
     *
     * @param  array<string, mixed>  $filters
     */
    public function changes(array $filters = [], int $perPage = 40): LengthAwarePaginator
    {
        return DB::table('activity_log')
            ->leftJoin('users', 'users.id', '=', 'activity_log.causer_id')
            ->when($filters['who'] ?? null, fn ($q, $id) => $q->where('causer_id', $id))
            ->when($filters['subject'] ?? null, fn ($q, $type) => $q->where('subject_type', 'like', "%\\\\{$type}"))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('activity_log.created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('activity_log.created_at', '<=', $date))
            ->orderByDesc('activity_log.id')
            ->select([
                'activity_log.id',
                'activity_log.description',
                'activity_log.subject_type',
                'activity_log.subject_id',
                'activity_log.event',
                'activity_log.properties',
                'activity_log.created_at',
                'users.name as causer_name',
            ])
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => $this->presentChange($row));
    }

    /*
    |--------------------------------------------------------------------------
    | Turning rows into sentences
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, array<int, array{text: string, url: ?string}>>  $causes
     * @return array<string, mixed>
     */
    private function present(StockLedger $row, array $causes): array
    {
        $item = $row->item;
        $quantity = $item->quantity(abs($row->qty_delta));
        $type = $row->movement_type;
        $cause = $causes[$row->reference_type][$row->reference_id] ?? null;

        return [
            'id' => $row->id,
            'when' => $row->created_at->format('D j M Y, g:i a'),
            'when_short' => $row->created_at->format('j M, g:i a'),
            'branch' => $row->branch_name ?? $row->branch?->name,
            'item' => $item->name,
            'item_id' => $item->id,

            'what' => $type->label(),
            'type' => $type->value,
            'tone' => $this->tone($type, $row->qty_delta),

            // The sign is the point: did stock arrive or leave?
            'direction' => $row->qty_delta >= 0 ? 'in' : 'out',
            'amount' => ($row->qty_delta >= 0 ? '+' : '−').$quantity->forDisplay(),
            'balance_after' => $item->quantity($row->balance_after)->forDisplay(),

            'who' => $row->createdBy?->name ?? 'The system',
            // A hand correction has no cause to point at, so it carries its
            // own reason. That beats "Corrected by hand" and nothing else.
            'why' => $cause['text'] ?? ($row->note ?: $this->fallbackCause($type)),
            'why_url' => $cause['url'] ?? null,

            'unit_cost' => $row->unit_cost !== null ? (float) $row->unit_cost : null,
            'value' => $row->unit_cost !== null
                ? round(abs($row->qty_delta) * (float) $row->unit_cost, 2)
                : null,
        ];
    }

    /**
     * Resolve every reference in one pass per type, so a page of forty
     * movements costs a handful of queries rather than forty.
     *
     * @param  Collection<int, StockLedger>  $rows
     * @return array<string, array<int, array{text: string, url: ?string}>>
     */
    private function resolveCauses(Collection $rows): array
    {
        $byType = $rows
            ->filter(fn (StockLedger $row) => $row->reference_type && $row->reference_id)
            ->groupBy('reference_type')
            ->map(fn (Collection $group) => $group->pluck('reference_id')->unique()->all());

        $resolved = [];

        foreach ($byType as $type => $ids) {
            $resolved[$type] = match ($type) {
                'RequestLine' => $this->requestLineCauses($ids),
                'GoodsReceipt' => $this->goodsReceiptCauses($ids),
                'Wastage' => $this->wastageCauses($ids),
                'LocalPurchase' => $this->localPurchaseCauses($ids),
                'StockCountLine' => $this->stockCountCauses($ids),
                default => [],
            };
        }

        return $resolved;
    }

    /** @param array<int, int> $ids */
    private function requestLineCauses(array $ids): array
    {
        return DB::table('request_lines')
            ->join('requests', 'requests.id', '=', 'request_lines.request_id')
            ->join('branches', 'branches.id', '=', 'requests.from_branch_id')
            ->whereIn('request_lines.id', $ids)
            ->select('request_lines.id', 'requests.id as request_id', 'requests.request_number', 'branches.name as branch')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => [
                'text' => "Request {$row->request_number} from {$row->branch}",
                'url' => "/admin/requests?status=all&selected={$row->request_id}",
            ]])
            ->all();
    }

    /** @param array<int, int> $ids */
    private function goodsReceiptCauses(array $ids): array
    {
        return DB::table('goods_receipts')
            ->join('purchase_order_lines', 'purchase_order_lines.id', '=', 'goods_receipts.purchase_order_line_id')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->whereIn('goods_receipts.id', $ids)
            ->select('goods_receipts.id', 'purchase_orders.id as order_id', 'purchase_orders.po_number', 'suppliers.name as supplier')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => [
                'text' => "Delivery on {$row->po_number} from {$row->supplier}",
                'url' => "/admin/purchase/{$row->order_id}",
            ]])
            ->all();
    }

    /** @param array<int, int> $ids */
    private function wastageCauses(array $ids): array
    {
        return DB::table('wastage')
            ->whereIn('id', $ids)
            ->select('id', 'reason', 'note')
            ->get()
            ->mapWithKeys(function ($row) {
                $reason = \App\Enums\WastageReason::tryFrom($row->reason)?->label() ?? $row->reason;

                return [$row->id => [
                    'text' => $row->note ? "Thrown away: {$reason} — {$row->note}" : "Thrown away: {$reason}",
                    'url' => '/waste',
                ]];
            })
            ->all();
    }

    /** @param array<int, int> $ids */
    private function localPurchaseCauses(array $ids): array
    {
        return DB::table('local_purchases')
            ->whereIn('id', $ids)
            ->select('id', 'reason')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => [
                'text' => "Bought locally: {$row->reason}",
                'url' => '/local-purchases',
            ]])
            ->all();
    }

    /** @param array<int, int> $ids */
    private function stockCountCauses(array $ids): array
    {
        return DB::table('stock_count_lines')
            ->join('stock_counts', 'stock_counts.id', '=', 'stock_count_lines.stock_count_id')
            ->whereIn('stock_count_lines.id', $ids)
            ->select('stock_count_lines.id', 'stock_counts.note', 'stock_counts.counted_at')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => [
                'text' => $row->note ? "Stock count: {$row->note}" : 'Corrected after a stock count',
                'url' => null,
            ]])
            ->all();
    }

    private function fallbackCause(MovementType $type): string
    {
        return match ($type) {
            MovementType::Purchase => 'Bought in',
            MovementType::Adjustment => 'Corrected by hand',
            MovementType::Consumption => 'Used in the kitchen',
            default => 'No reason recorded',
        };
    }

    private function tone(MovementType $type, int $delta): string
    {
        return match (true) {
            $type === MovementType::Wastage => 'rejected',
            $type === MovementType::Adjustment => 'partial',
            $delta >= 0 => 'approved',
            default => 'sent',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function presentChange(object $row): array
    {
        $properties = json_decode($row->properties ?? '{}', true) ?: [];
        $after = $properties['attributes'] ?? [];
        $before = $properties['old'] ?? [];

        $fields = [];

        foreach ($after as $key => $newValue) {
            $oldValue = $before[$key] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $fields[] = [
                'field' => $this->fieldLabel($key),
                'from' => $this->readable($oldValue),
                'to' => $this->readable($newValue),
            ];
        }

        return [
            'id' => $row->id,
            'when' => \Illuminate\Support\Carbon::parse($row->created_at)->format('D j M Y, g:i a'),
            'who' => $row->causer_name ?? 'The system',
            'what' => class_basename((string) $row->subject_type),
            'event' => $row->event ?? $row->description,
            'subject_id' => $row->subject_id,
            'fields' => $fields,
        ];
    }

    private function fieldLabel(string $key): string
    {
        return match ($key) {
            'is_active' => 'Switched on',
            'cutoff_time' => 'Cut-off time',
            'par_level' => 'Full shelf',
            'reorder_level' => 'Running low under',
            'conversion_factor' => 'Units per order unit',
            'order_unit' => 'Ordered in',
            'category_id' => 'Group',
            'cancel_reason' => 'Reason for cancelling',
            'reviewed_by' => 'Looked at by',
            'dispatched_by' => 'Sent by',
            'received_by' => 'Confirmed by',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    private function readable(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => json_encode($value),
            default => (string) $value,
        };
    }
}
