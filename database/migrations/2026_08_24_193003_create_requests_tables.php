<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 30)->unique();

            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();

            $table->enum('status', [
                'draft', 'waiting', 'approved', 'partial', 'rejected',
                'sent', 'received', 'closed', 'cancelled',
            ])->default('draft');

            $table->date('needed_by')->nullable();
            $table->text('note')->nullable();

            /*
             * A branch may send a request at any hour, as many times a day as
             * it needs. The cut-off never blocks anything - it only marks a
             * request Late so the admin sees it first. The cut-off in force at
             * the time is snapshotted here so editing it later cannot rewrite
             * history.
             */
            $table->boolean('is_late')->default(false);
            $table->timestamp('cutoff_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();

            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();

            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['to_branch_id', 'status']);
            $table->index(['from_branch_id', 'status']);
            $table->index(['status', 'is_late', 'submitted_at']);
        });

        Schema::create('request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();

            /*
             * Four numbers, all in base units. The gaps between them are the
             * whole point of the system:
             *   asked -> approved -> sent -> arrived
             */
            $table->unsignedBigInteger('qty_requested');
            $table->unsignedBigInteger('qty_approved')->nullable();
            $table->unsignedBigInteger('qty_sent')->nullable();
            $table->unsignedBigInteger('qty_received')->nullable();

            $table->enum('line_status', [
                'waiting', 'approved', 'reduced', 'rejected', 'sent', 'received',
            ])->default('waiting');

            // Required whenever a line is cut or rejected.
            $table->enum('reason_code', [
                'out_of_stock', 'too_much_asked', 'not_needed_today', 'other',
            ])->nullable();

            $table->string('admin_note')->nullable();
            $table->string('branch_note')->nullable();

            $table->timestamps();

            $table->unique(['request_id', 'item_id']);
        });

        Schema::create('dispatch_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->string('note_number', 30)->unique();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('carrier_name')->nullable();
            $table->string('vehicle_number', 30)->nullable();
            $table->timestamp('sent_at');
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::create('receipt_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_line_id')->constrained()->cascadeOnDelete();

            // In base units.
            $table->unsignedBigInteger('qty_short');

            $table->enum('reason', ['damaged', 'missing', 'wrong_item', 'expired']);
            $table->string('note')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_discrepancies');
        Schema::dropIfExists('dispatch_notes');
        Schema::dropIfExists('request_lines');
        Schema::dropIfExists('requests');
    }
};
