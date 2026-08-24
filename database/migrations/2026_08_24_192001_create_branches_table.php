<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->enum('type', ['main', 'sub'])->default('sub');
            $table->string('address')->nullable();
            $table->string('phone', 20)->nullable();

            // The daily cut-off for next-day requests. A request sent after this
            // is flagged Late and pinned to the top of the admin list. It never
            // blocks sending - a branch can ask for stock at any hour.
            $table->time('cutoff_time')->default('18:00:00');
            $table->string('timezone', 64)->default('Asia/Kolkata');

            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
