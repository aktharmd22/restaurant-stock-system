<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->boolean('is_active')->default(true)->after('password');
            $table->boolean('sound_enabled')->default(true)->after('is_active');
            $table->unsignedTinyInteger('sound_volume')->default(80)->after('sound_enabled');
            $table->timestamp('last_login_at')->nullable()->after('sound_volume');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn([
                'phone', 'is_active', 'sound_enabled', 'sound_volume', 'last_login_at', 'deleted_at',
            ]);
        });
    }
};
