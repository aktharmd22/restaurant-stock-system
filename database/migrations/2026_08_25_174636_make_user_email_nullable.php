<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A person signs in with a phone number or an email, whichever they have.
 *
 * Laravel's own users table makes email NOT NULL, which meant the People form
 * could offer "Email (optional)" and then fail with a database error the
 * moment anyone took it at its word. Kitchen staff often have no email at all,
 * so the column follows the sign-in screen rather than the other way round.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Anyone without an email would block the column going back to NOT
        // NULL, so give them a placeholder derived from their phone number.
        DB::table('users')
            ->whereNull('email')
            ->update(['email' => DB::raw("CONCAT(COALESCE(phone, id), '@no-email.local')")]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
