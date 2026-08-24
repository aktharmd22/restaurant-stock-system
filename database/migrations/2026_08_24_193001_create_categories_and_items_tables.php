<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');

            // Everything in the system is stored in the base unit as a whole
            // number: grams, millilitres or pieces. Order units are for people.
            $table->enum('base_unit', ['g', 'ml', 'piece']);
            $table->enum('order_unit', ['kg', 'g', 'litre', 'ml', 'sack', 'piece', 'dozen', 'packet']);

            // How many base units are in one order unit. 1 kg -> 1000 g.
            $table->unsignedInteger('conversion_factor')->default(1);

            // How far the stepper moves on one tap, in ORDER units, times 100.
            // Stored as an integer so no float ever reaches the database.
            $table->unsignedInteger('step_x100')->default(100);

            $table->boolean('is_perishable')->default(false);
            $table->unsignedSmallInteger('shelf_life_days')->nullable();

            // Groups the pack list so the store keeper walks the store once.
            $table->string('storage_location')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index('storage_location');
        });

        Schema::create('branch_item_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();

            // Both in base units. Par level is the "full shelf" number used to
            // suggest a quantity; reorder level is when we call it running low.
            $table->unsignedBigInteger('par_level')->default(0);
            $table->unsignedBigInteger('reorder_level')->default(0);

            $table->timestamps();

            $table->unique(['branch_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_item_settings');
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
    }
};
