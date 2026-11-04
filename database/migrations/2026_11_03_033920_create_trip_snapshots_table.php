<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->unique()->constrained();
            $table->decimal('revenue', 15, 2);
            $table->decimal('standard_cost', 15, 2);
            $table->decimal('negotiated_cost', 15, 2);
            $table->decimal('actual_cost', 15, 2);
            $table->decimal('vendor_saving', 15, 2);
            $table->decimal('customer_discount', 15, 2)->nullable();
            $table->decimal('actual_profit', 15, 2);
            // Diisi pada Sprint 7 (marketing allocation dan bagi hasil)
            $table->decimal('marketing_percent', 7, 4)->nullable();
            $table->decimal('marketing_allocation', 15, 2)->nullable();
            $table->decimal('distributable_profit', 15, 2)->nullable();
            $table->decimal('owner_percent', 7, 4)->nullable();
            $table->decimal('owner_share', 15, 2)->nullable();
            $table->decimal('partner_percent', 7, 4)->nullable();
            $table->decimal('partner_share', 15, 2)->nullable();
            $table->decimal('outstanding_at_reconcile', 15, 2)->default(0);
            $table->text('reconcile_note')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_snapshots');
    }
};