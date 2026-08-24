<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Gap-free, race-free document numbers.
 *
 * MAX(number) + 1 breaks the moment two branches send a request in the same
 * second, and people do notice when two requests share a number.
 */
class SequenceService
{
    /** REQ-PARK-0007 */
    public function requestNumber(string $branchCode): string
    {
        return sprintf('REQ-%s-%04d', $branchCode, $this->next("request:{$branchCode}"));
    }

    /** DN-0007 */
    public function dispatchNoteNumber(): string
    {
        return sprintf('DN-%04d', $this->next('dispatch_note'));
    }

    /** PO-0007 */
    public function purchaseOrderNumber(): string
    {
        return sprintf('PO-%04d', $this->next('purchase_order'));
    }

    /**
     * Take the next value for a key, locking the row so two callers cannot
     * take the same one.
     */
    public function next(string $key): int
    {
        return DB::transaction(function () use ($key) {
            $row = DB::table('number_sequences')->where('key', $key)->lockForUpdate()->first();

            if (! $row) {
                DB::table('number_sequences')->insert(['key' => $key, 'next_value' => 2]);

                return 1;
            }

            DB::table('number_sequences')->where('key', $key)->update([
                'next_value' => $row->next_value + 1,
            ]);

            return (int) $row->next_value;
        });
    }
}
