<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A colour per item group, so a branch can find vegetables on the ask
     * screen without reading a single word.
     *
     * Stored as a name rather than a hex value: the palette lives in the design
     * system, and a stored hex would let someone pick a colour that fails the
     * contrast floor.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('colour', 20)->default('slate')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('colour');
        });
    }
};
