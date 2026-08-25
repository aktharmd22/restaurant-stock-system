<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per "this much of this line turned up today".
     *
     * A purchase order line can be delivered more than once, so the ledger
     * cannot reference the line itself - the idempotency index would treat the
     * second delivery as a duplicate of the first and silently drop the stock.
     * It references one of these instead, which is also a straight answer to
     * "when did that actually arrive?".
     */
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_line_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('qty'); // base units
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['purchase_order_line_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
