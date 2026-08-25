<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A token the phone generates before sending.
     *
     * Without it, an offline retry queue is dangerous: replaying a "send
     * request" that actually did reach the server would create a second
     * request, and the branch would get double the stock. With it, the retry
     * is safe - the server recognises the token and hands back the request it
     * already made.
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->string('client_token', 40)->nullable()->unique()->after('request_number');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn('client_token');
        });
    }
};
