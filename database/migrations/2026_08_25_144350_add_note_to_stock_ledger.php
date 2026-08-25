<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somewhere to say why a number was corrected by hand.
 *
 * Every other movement carries its reason on the thing that caused it - a
 * request, a wastage entry, a stock count. A one-off correction has no such
 * thing, and "Corrected by hand" with no reason is exactly the entry someone
 * will be staring at in three months wondering what happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_ledger', function (Blueprint $table) {
            $table->string('note', 160)->nullable()->after('reference_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_ledger', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
