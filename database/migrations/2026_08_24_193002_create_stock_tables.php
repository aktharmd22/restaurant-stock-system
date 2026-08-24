<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The ledger is the truth. Every stock movement in the system writes
         * exactly one immutable row here. Nothing updates a row; nothing
         * deletes one. If the balances ever look wrong, they are rebuilt from
         * this table with `php artisan stock:rebuild-balances`.
         */
        Schema::create('stock_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();

            // Signed, in base units. Positive adds, negative removes.
            $table->bigInteger('qty_delta');

            $table->enum('movement_type', [
                'purchase',
                'transfer_in',
                'transfer_out',
                'consumption',
                'wastage',
                'adjustment',
                'return',
            ]);

            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->bigInteger('balance_after');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['branch_id', 'item_id', 'id']);
            $table->index(['branch_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);

            /*
             * Idempotency. Branch users on bad connections double-tap buttons
             * and retried requests arrive twice. This makes a repeated movement
             * a duplicate-key error instead of a second helping of stock.
             * Rows with no reference (manual adjustments) are exempt, because
             * MySQL allows repeated NULLs in a unique index.
             */
            $table->unique(
                ['branch_id', 'item_id', 'movement_type', 'reference_type', 'reference_id'],
                'ledger_movement_unique',
            );
        });

        /*
         * A cache of the ledger, never a source of truth. Only
         * StockLedgerService writes here, always under a row lock.
         */
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();

            $table->bigInteger('qty_on_hand')->default(0);

            // Approved but not yet dispatched. Available = on hand - reserved.
            $table->bigInteger('qty_reserved')->default(0);

            $table->decimal('avg_cost', 14, 4)->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->unique(['branch_id', 'item_id']);
        });

        /*
         * Gap-free document numbers without a MAX()+1 race.
         */
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->unsignedBigInteger('next_value')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_ledger');
    }
};
